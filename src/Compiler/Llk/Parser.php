<?php declare(strict_types=1);

namespace Hoa\Compiler\Llk;

use Hoa\Compiler;
use Hoa\Compiler\Exceptions\UnexpectedTokenException;
use Hoa\Compiler\Llk\Rules\Choice;
use Hoa\Compiler\Llk\Rules\Concatenation;
use Hoa\Compiler\Llk\Rules\Ekzit;
use Hoa\Compiler\Llk\Rules\Entry;
use Hoa\Compiler\Llk\Rules\Invocation;
use Hoa\Compiler\Llk\Rules\Repetition;
use Hoa\Compiler\Llk\Rules\Rule;
use Hoa\Compiler\Llk\Rules\Token;
use Hoa\Iterator\Buffer;
use Hoa\Iterator\Lookahead;
use RuntimeException;

/**
 * LL(k) parser.
 */
class Parser
{
    /**
     * List of pragmas.
     * @var array<string,string|int|bool>
     */
    protected array $_pragmas;

    /**
     * List of skipped tokens.
     */
    protected ?array $_skip = null;

    /**
     * Associative array (token name => token regex), to be defined in precedence order.
     * @var array<string,array<string,string>>
     */
    protected array $_tokens;

    /**
     * Rules, to be defined as associative array, name => Rule object.
     * @var array<Rule>
     */
    protected array $_rules;

    /**
     * Lexer iterator.
     */
    protected Buffer|Lookahead|null $_tokenSequence = null;

    /**
     * Possible token causing an error.
     */
    protected ?array $_errorToken = null;

    /**
     * Trace of activated rules.
     * @var array<Invocation|Rule>
     */
    protected array $trace = [];

    /**
     * Stack of todo list.
     * @var ?array<Invocation>
     */
    protected ?array $_todo = null;

    /**
     * AST.
     */
    protected ?TreeNode $_tree = null;

    /**
     * Current depth while building the trace.
     */
    protected int $_depth = -1;

    /**
     * Construct the parser.
     *
     * @param array<string,array<string,string>> $tokens Tokens.
     * @param array<Rule> $rules Rules.
     * @param array<string,string|int|bool> $pragmas Pragmas.
     */
    public function __construct(
        array $tokens = [],
        array $rules = [],
        array $pragmas = []
    ) {
        $this->_tokens = $tokens;
        $this->_rules = $rules;
        $this->_pragmas = $pragmas;
    }

    /**
     * Parse :-).
     *
     * @param string $text Text to parse.
     * @param string|null $rule The axiom, i.e. root rule.
     * @param bool $tree Whether build tree or not.
     * @return TreeNode
     * @throws UnexpectedTokenException
     * @psalm-suppress MixedArrayAccess
     * @psalm-suppress MixedAssignment
     */
    public function parse(string $text, string $rule = null, bool $tree = true): TreeNode
    {
        $k = 1024;

        if (isset($this->_pragmas['parser.lookahead'])) {
            $k = max(0, intval($this->_pragmas['parser.lookahead']));
        }

        $lexer = new Lexer($this->_pragmas);
        $this->_tokenSequence = new Buffer(
            $lexer->lexMe($text, $this->_tokens),
            $k
        );
        $this->_tokenSequence->rewind();

        $this->_errorToken = null;
        $this->trace = [];
        $this->_todo = [];

        if ($rule === null || false === array_key_exists($rule, $this->_rules)) {
            $rule = $this->getRootRule();
        }

        $closeRule = new Ekzit($rule, 0);
        $openRule = new Entry($rule, 0, [$closeRule]);
        $this->_todo = [$closeRule, $openRule];

        do {
            $out = $this->unfold();

            if (null !== $out &&
                'EOF' === $this->_tokenSequence->current()['token']) {
                break;
            }

            if (false === $this->backtrack()) {
                $token = $this->_errorToken;

                if (null === $this->_errorToken) {
                    $token = $this->_tokenSequence->current();
                }

                assert($token !== null);

                $offset = $token['offset'];

                assert(is_int($offset));

                $line = 1;
                $column = 1;

                if (!empty($text)) {
                    if (0 === $offset) {
                        $leftnl = 0;
                    } else {
                        $leftnl = strrpos($text, "\n", -(strlen($text) - $offset) - 1) ?: 0;
                    }

                    // assert($offset !== null);
                    $rightnl = strpos($text, "\n", $offset);
                    $line = substr_count($text, "\n", 0, $leftnl + 1) + 1;
                    $column = $offset - $leftnl + (0 === $leftnl ? 1 : 0);

                    if (false !== $rightnl) {
                        $text = trim(substr($text, $leftnl, $rightnl - $leftnl), "\n");
                    }
                }

                $message = sprintf(
                    'Unexpected token "%s" (%s) at line %d and column %d:' .
                    "\n" . '%s' . "\n" . str_repeat(' ', $column - 1) . '↑',
                    $token ? (string) $token['value'] : '',
                    $token ? (string) $token['token'] : '',
                    $line,
                    $column,
                    $text
                );
                throw new UnexpectedTokenException($message, 0, $line, $column);
            }
        } while (true);

        if (false === $tree) {
            throw new RuntimeException('Should never happen.');
        }

        $tree = $this->_buildTree();

        if (!($tree instanceof TreeNode)) {
            throw new Compiler\Exceptions\Exception(
                'Parsing error: cannot build AST, the trace is corrupted.',
                1
            );
        }

        return $this->_tree = $tree;
    }

    /**
     * Unfold trace.
     * @psalm-suppress PossiblyNullArgument
     */
    protected function unfold(): ?bool
    {
        while (0 < count($this->_todo)) {
            $rule = array_pop($this->_todo);

            if ($rule instanceof Ekzit) {
                $rule->setDepth($this->_depth);
                $this->trace[] = $rule;

                if (false === $rule->isTransitional()) {
                    --$this->_depth;
                }
            } else {
                $ruleName = $rule->getRule();
                $next = $rule->getData();
                assert($ruleName !== null);
                $zeRule = $this->_rules[$ruleName];
                $out = $this->_parse($zeRule, $next);

                if (false === $out && false === $this->backtrack()) {
                    return null;
                }
            }
        }

        return true;
    }

    /**
     * Parse current rule.
     * @param Rule $currentRule Current rule.
     * @param string|int $nextRuleIndex Next rule index.
     * @return bool
     * @psalm-suppress MixedArrayAccess
     * @psalm-suppress MixedAssignment
     */
    protected function _parse(Rule $currentRule, string|int $nextRuleIndex): bool
    {
        assert($this->_tokenSequence !== null);

        if ($currentRule instanceof Token) {
            $name = $this->_tokenSequence->current()['token'];

            if ($currentRule->getTokenName() !== $name) {
                return false;
            }

            $value = $this->_tokenSequence->current()['value'];

            if (0 <= $unification = $currentRule->getUnificationIndex()) {
                for ($skip = 0, $i = count($this->trace) - 1; $i >= 0; --$i) {
                    $trace = $this->trace[$i];

                    if ($trace instanceof Entry) {
                        if (false === $trace->isTransitional()) {
                            if ($trace->getDepth() <= $this->_depth) {
                                break;
                            }

                            --$skip;
                        }
                    } elseif ($trace instanceof Ekzit &&
                        false === $trace->isTransitional()) {
                        $skip += (int)($trace->getDepth() > $this->_depth);
                    }

                    if (0 < $skip) {
                        continue;
                    }

                    if ($trace instanceof Token &&
                        $unification === $trace->getUnificationIndex() &&
                        $value !== $trace->getValue()) {
                        return false;
                    }
                }
            }

            $namespace = $this->_tokenSequence->current()['namespace'];
            $zzeRule = clone $currentRule;
            assert(is_string($value));
            $zzeRule->setValue($value);
            assert(is_string($namespace));
            $zzeRule->setNamespace($namespace);

            /**
             * @psalm-suppress MixedArrayOffset
             */
            if (isset($this->_tokens[$namespace][$name])) {
                $zzeRule->setRepresentation($this->_tokens[$namespace][$name]);
            } else {
                foreach ($this->_tokens[$namespace] as $_name => $regex) {
                    assert(is_string($_name));

                    if (false === $pos = strpos($_name, ':')) {
                        continue;
                    }

                    $_name = substr($_name, 0, $pos);

                    if ($_name === $name) {
                        break;
                    }
                }
                assert(isset($regex) && is_string($regex));
                $zzeRule->setRepresentation($regex);
            }

            assert($this->_todo !== null);

            array_pop($this->_todo);
            $this->trace[] = $zzeRule;
            $this->_tokenSequence->next();
            $this->_errorToken = $this->_tokenSequence->current();

            return true;
        } elseif ($currentRule instanceof Concatenation) {
            if (false === $currentRule->isTransitional()) {
                ++$this->_depth;
            }

            $this->trace[] = new Entry(
                $currentRule->getName(),
                0,
                null,
                $this->_depth
            );
            $children = $currentRule->getChildren();

            assert(is_array($children));
            for ($i = count($children) - 1; $i >= 0; --$i) {
                $nextRule = $children[$i];
                assert(is_scalar($nextRule));
                $this->_todo[] = new Ekzit($nextRule, 0);
                $this->_todo[] = new Entry($nextRule, 0);
            }

            return true;
        } elseif ($currentRule instanceof Choice) {
            $children = $currentRule->getChildren();
            assert(is_array($children));
            if ($nextRuleIndex >= count($children)) {
                return false;
            }

            if (false === $currentRule->isTransitional()) {
                ++$this->_depth;
            }

            $this->trace[] = new Entry(
                $currentRule->getName(),
                $nextRuleIndex,
                $this->_todo,
                $this->_depth
            );
            $nextRule = $children[$nextRuleIndex];
            assert(is_scalar($nextRule));
            $this->_todo[] = new Ekzit($nextRule, 0);
            $this->_todo[] = new Entry($nextRule, 0);

            return true;
        } elseif ($currentRule instanceof Repetition) {
            $nextRule = $currentRule->getChildren();

            if (0 === $nextRuleIndex) {
                $name = $currentRule->getName();
                $min = $currentRule->getMin();

                if (false === $currentRule->isTransitional()) {
                    ++$this->_depth;
                }

                $this->trace[] = new Entry(
                    $name,
                    $min,
                    null,
                    $this->_depth
                );
                assert($this->_todo !== null);
                array_pop($this->_todo);
                $this->_todo[] = new Ekzit(
                    $name,
                    $min,
                    $this->_todo
                );

                for ($i = 0; $i < $min; ++$i) {
                    assert(is_scalar($nextRule));
                    $this->_todo[] = new Ekzit($nextRule, 0);
                    $this->_todo[] = new Entry($nextRule, 0);
                }

                return true;
            } else {
                $max = $currentRule->getMax();

                if (-1 != $max && $nextRuleIndex > $max) {
                    return false;
                }

                $this->_todo[] = new Ekzit(
                    $currentRule->getName(),
                    $nextRuleIndex,
                    $this->_todo
                );
                assert(is_scalar($nextRule));
                $this->_todo[] = new Ekzit($nextRule, 0);
                $this->_todo[] = new Entry($nextRule, 0);

                return true;
            }
        }

        return false;
    }

    /**
     * Backtrack the trace.
     */
    protected function backtrack(): bool
    {
        $found = false;

        do {
            $last = array_pop($this->trace);
            if ($last instanceof Entry) {
                $zeRule = $this->_rules[$last->getRule()];
                $found = $zeRule instanceof Choice;
            } elseif ($last instanceof Ekzit) {
                $zeRule = $this->_rules[$last->getRule()];
                $found = $zeRule instanceof Repetition;
            } elseif ($last instanceof Token) {
                /**
                 * @psalm-suppress PossiblyUndefinedMethod
                 * @psalm-suppress PossiblyNullReference
                 */
                $this->_tokenSequence->previous();

                if (false === $this->_tokenSequence?->valid()) {
                    return false;
                }
            }
        } while (0 < count($this->trace) && false === $found);

        if (false === $found) {
            return false;
        }

        assert($last instanceof Invocation);
        $rule = $last->getRule();
        $next = ((int) $last->getData()) + 1;
        $this->_depth = $last->getDepth();
        $this->_todo = $last->getTodo();
        assert($rule !== null);
        $this->_todo[] = new Entry($rule, $next);

        return true;
    }

    /**
     * Build AST from trace.
     * Walk through the trace iteratively and recursively.
     *
     * @param int $i Current trace index.
     * @param array &$children<TreeNode> Collected children.
     * @return TreeNode|int
     * @psalm-suppress ArgumentTypeCoercion
     * @psalm-suppress MixedArgumentTypeCoercion
     * @psalm-suppress MixedArgument
     * @psalm-suppress MixedAssignment
     */
    protected function _buildTree(int $i = 0, array &$children = []): TreeNode|int
    {
        $max = count($this->trace);

        while ($i < $max) {
            assert(is_int($i));
            $trace = $this->trace[$i];

            if ($trace instanceof Entry) {
                $ruleName = $trace->getRule();
                assert($ruleName !== null);
                $rule = $this->_rules[$ruleName];
                $isRule = false === $trace->isTransitional();
                $nextTrace = $this->trace[$i + 1];
                $id = $rule->getNodeId();

                // Optimization: Skip empty trace sequence.
                if ($nextTrace instanceof Ekzit &&
                    $ruleName == $nextTrace->getRule()) {
                    $i += 2;

                    continue;
                }

                if (true === $isRule) {
                    $children[] = $ruleName;
                }

                if (null !== $id) {
                    $children[] = [
                        'id' => $id,
                        'options' => $rule->getNodeOptions()
                    ];
                }

                $i = $this->_buildTree($i + 1, $children);

                if (false === $isRule) {
                    continue;
                }

                $handle = [];
                $cId = null;
                $cOptions = [];

                do {
                    $pop = array_pop($children);

                    if (true === is_object($pop)) {
                        $handle[] = $pop;
                    } elseif (true === is_array($pop) && null === $cId) {
                        $cId = $pop['id'];
                        $cOptions = $pop['options'];
                    } elseif ($ruleName == $pop) {
                        break;
                    }
                } while (null !== $pop);

                if (null === $cId) {
                    $cId = $rule->getDefaultId();
                    $cOptions = $rule->getDefaultOptions();
                }

                if (null === $cId) {
                    for ($j = count($handle) - 1; $j >= 0; --$j) {
                        $children[] = $handle[$j];
                    }

                    continue;
                }

                if (true === in_array('M', $cOptions) &&
                    true === $this->mergeTree($children, $handle, $cId, false)) {
                    continue;
                }

                if (true === in_array('m', $cOptions) &&
                    true === $this->mergeTree($children, $handle, $cId, true)) {
                    continue;
                }

                $cTree = new TreeNode($id ?: $cId);

                foreach ($handle as $child) {
                    assert($child instanceof TreeNode);
                    $child->setParent($cTree);
                    $cTree->prependChild($child);
                }

                $children[] = $cTree;
            } elseif ($trace instanceof Ekzit) {
                return $i + 1;
            } elseif ($trace instanceof Token) {
                if (false === $trace->isKept()) {
                    ++$i;
                    continue;
                }
                $child = new TreeNode('token', [
                    'token' => $trace->getTokenName(),
                    'value' => $trace->getValue(),
                    'namespace' => $trace->getNamespace(),
                ]);
                $children[] = $child;
                ++$i;
            } else {
                throw new RuntimeException('Unknown rule');
            }
        }

        $first = $children[0];
        assert($first instanceof TreeNode);
        return $first;
    }

    /**
     * Try to merge directly children into an existing node.
     *
     * @param array<TreeNode> &$children Current children being gathering.
     * @param array<TreeNode> &$handle Children of the new node.
     * @param string $cId Node ID.
     * @param bool $recursive Whether we should merge recursively or
     *                                not.
     * @return  bool
     */
    protected function mergeTree(array &$children, array &$handle, string $cId, bool $recursive): bool
    {
        end($children);
        $last = current($children);

        if (!is_object($last)) {
            return false;
        }

        if ($cId !== $last->getId()) {
            return false;
        }

        if (true === $recursive) {
            foreach ($handle as $child) {
                $this->mergeTreeRecursive($last, $child);
            }

            return true;
        }

        foreach ($handle as $child) {
            $last->appendChild($child);
            $child->setParent($last);
        }

        return true;
    }

    /**
     * Merge recursively.
     * Please, see self::mergeTree() to know the context.
     *
     * @param TreeNode $node Node that receives.
     * @param TreeNode $newNode Node to merge.
     * @return  void
     */
    protected function mergeTreeRecursive(TreeNode $node, TreeNode $newNode)
    {
        $nNId = $newNode->getId();

        if ('token' === $nNId) {
            $node->appendChild($newNode);
            $newNode->setParent($node);

            return;
        }

        $children = $node->getChildren();
        end($children);
        $last = current($children);

        if ($last->getId() !== $nNId) {
            $node->appendChild($newNode);
            $newNode->setParent($node);

            return;
        }

        foreach ($newNode->getChildren() as $child) {
            $this->mergeTreeRecursive($last, $child);
        }
    }

    /**
     * Get AST.
     */
    public function getTree(): ?TreeNode
    {
        return $this->_tree;
    }

    /**
     * Get trace.
     */
    public function getTrace(): array
    {
        return $this->trace;
    }

    /**
     * Get pragmas.
     * @return array<string,string|int|bool>
     */
    public function getPragmas(): array
    {
        return $this->_pragmas;
    }

    /**
     * Get tokens.
     * @return array<string,array<string>>
     */
    public function getTokens(): array
    {
        return $this->_tokens;
    }

    /**
     * Get the lexer iterator.
     */
    public function getTokenSequence(): Lookahead|Buffer|null
    {
        return $this->_tokenSequence;
    }

    /**
     * Get rule by name
     */
    public function getRule(string|int $name): ?Rule
    {
        if (!isset($this->_rules[$name])) {
            return null;
        }
        return $this->_rules[$name];
    }

    /**
     * Get rules.
     * @return array<Rule>
     */
    public function getRules(): array
    {
        return $this->_rules;
    }

    /**
     * Get root rule.
     */
    public function getRootRule(): int|string
    {
        foreach ($this->_rules as $rule => $_) {
            if (!is_int($rule)) {
                return $rule;
            }
        }
        throw new RuntimeException('Should never happen');
    }
}
