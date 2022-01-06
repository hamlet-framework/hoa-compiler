<?php declare(strict_types=1);

namespace Hoa\Compiler\Llk;

use Hoa\Compiler;
use Hoa\Compiler\Exceptions\UnexpectedTokenException;
use Hoa\Compiler\Llk\Rules\ChoiceRule;
use Hoa\Compiler\Llk\Rules\ConcatenationRule;
use Hoa\Compiler\Llk\Rules\Entry;
use Hoa\Compiler\Llk\Rules\ExitRule;
use Hoa\Compiler\Llk\Rules\InvocationRule;
use Hoa\Compiler\Llk\Rules\RepetitionRule;
use Hoa\Compiler\Llk\Rules\Rule;
use Hoa\Compiler\Llk\Rules\TokenRule;
use Hoa\Iterator\Buffer;
use RuntimeException;

/**
 * LL(k) parser.
 */
final class Parser
{
    /**
     * Possible token causing an error.
     */
    private ?Token $_errorToken = null;

    /**
     * Trace of activated rules.
     * @var array<InvocationRule|Rule>
     */
    private array $trace = [];

    /**
     * Stack of todo list.
     * @var array<InvocationRule>
     */
    private array $todo = [];

    /**
     * AST.
     */
    private ?TreeNode $_tree = null;

    /**
     * Current depth while building the trace.
     */
    private int $depth = -1;

    /**
     * @param array<string,array<string,string>> $tokens Associative array (token name => token regex), to be defined in precedence order.
     * @param array<Rule> $rules Rules, to be defined as associative array, name(which can be numerical) => Rule object.
     * @param array<string,string|int|bool> $pragmas List of pragmas.
     */
    public function __construct(
        private array $tokens = [],
        private array $rules = [],
        private array $pragmas = []
    ) {
    }

    /**
     * Parse :-).
     *
     * @param string $text Text to parse.
     * @param string|null $rule The axiom, i.e. root rule.
     * @param bool $asTree Whether build tree or not.
     * @return TreeNode
     * @throws UnexpectedTokenException
     */
    public function parse(string $text, string $rule = null, bool $asTree = true): TreeNode
    {
        $bufferSize = 1024;
        if (isset($this->pragmas['parser.lookahead'])) {
            $bufferSize = max(0, intval($this->pragmas['parser.lookahead']));
        }
        $lexer = new Lexer($this->pragmas);
        $tokenSequence = new Buffer($lexer->run($text, $this->tokens), $bufferSize);
        $tokenSequence->rewind();

        $this->_errorToken = null;
        $this->trace = [];
        $this->todo = [];

        if ($rule === null || !array_key_exists($rule, $this->rules)) {
            $rule = $this->getRootRule();
        }

        $closeRule = new ExitRule($rule, 0);
        $openRule = new Entry($rule, 0, [$closeRule]);
        $this->todo = [$closeRule, $openRule];

        do {
            $out = $this->unfold($tokenSequence);

            if ($out && $tokenSequence->current()->token === 'EOF') {
                break;
            }

            if (!$this->backtrack($tokenSequence)) {
                $token = $this->_errorToken;

                /**
                 * @psalm-suppress RedundantCondition
                 */
                if (null === $this->_errorToken) {
                    $token = $tokenSequence->current();
                }

                assert($token !== null);

                $offset = $token->offset;

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
                    $token->value,
                    $token->token,
                    $line,
                    $column,
                    $text
                );
                throw new UnexpectedTokenException($message, 0, $line, $column);
            }
        } while (true);

        if (!$asTree) {
            throw new RuntimeException('Should never happen.');
        }

        $tree = $this->_buildTree();
        if (!($tree instanceof TreeNode)) {
            throw new Compiler\Exceptions\Exception('Parsing error: cannot build AST, the trace is corrupted.', 1);
        }
        return $this->_tree = $tree;
    }

    /**
     * Unfold trace.
     * @param Buffer<mixed,Token> $tokenSequence
     * @return bool
     */
    private function unfold(Buffer $tokenSequence): bool
    {
        while ($this->todo) {
            $rule = array_pop($this->todo);
            if ($rule instanceof ExitRule) {
                $rule->setDepth($this->depth);
                $this->trace[] = $rule;
                if (!$rule->isTransitional()) {
                    $this->depth--;
                }
            } else {
                $ruleName = $rule->getRule();
                $next = $rule->getData();
                $zeRule = $this->rules[$ruleName];
                $out = $this->_parse($tokenSequence, $zeRule, $next);
                if (!$out && !$this->backtrack($tokenSequence)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Parse current rule.
     * @param Buffer<mixed,Token> $tokenSequence
     * @param Rule $currentRule Current rule.
     * @param string|int $nextRuleIndex Next rule index.
     * @return bool
     * @psalm-suppress MixedAssignment
     */
    private function _parse(Buffer $tokenSequence, Rule $currentRule, string|int $nextRuleIndex): bool
    {
        if ($currentRule instanceof TokenRule) {
            $name = $tokenSequence->current()->token;

            if ($currentRule->getTokenName() !== $name) {
                return false;
            }

            $value = $tokenSequence->current()->value;

            if (0 <= $unification = $currentRule->getUnificationIndex()) {
                for ($skip = 0, $i = count($this->trace) - 1; $i >= 0; --$i) {
                    $trace = $this->trace[$i];

                    if ($trace instanceof Entry) {
                        if (false === $trace->isTransitional()) {
                            if ($trace->getDepth() <= $this->depth) {
                                break;
                            }

                            --$skip;
                        }
                    } elseif ($trace instanceof ExitRule &&
                        false === $trace->isTransitional()) {
                        $skip += (int)($trace->getDepth() > $this->depth);
                    }

                    if (0 < $skip) {
                        continue;
                    }

                    if ($trace instanceof TokenRule &&
                        $unification === $trace->getUnificationIndex() &&
                        $value !== $trace->getValue()) {
                        return false;
                    }
                }
            }

            $namespace = $tokenSequence->current()->namespace;
            $zzeRule = clone $currentRule;
            $zzeRule->setValue($value);
            $zzeRule->setNamespace($namespace);

            if (isset($this->tokens[$namespace][$name])) {
                $zzeRule->setRepresentation($this->tokens[$namespace][$name]);
            } else {
                foreach ($this->tokens[$namespace] as $_name => $regex) {
                    $pos = strpos($_name, ':');
                    if ($pos === false) {
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

            array_pop($this->todo);
            $this->trace[] = $zzeRule;
            $tokenSequence->next();
            $this->_errorToken = $tokenSequence->current();

            return true;
        } elseif ($currentRule instanceof ConcatenationRule) {
            if (!$currentRule->isTransitional()) {
                ++$this->depth;
            }

            $this->trace[] = new Entry($currentRule->getName(), 0, [], $this->depth);
            $children = $currentRule->getChildren();

            assert(is_array($children));
            for ($i = count($children) - 1; $i >= 0; --$i) {
                $nextRule = $children[$i];
                assert(is_string($nextRule) || is_int($nextRule));
                $this->todo[] = new ExitRule($nextRule, 0);
                $this->todo[] = new Entry($nextRule, 0);
            }

            return true;
        } elseif ($currentRule instanceof ChoiceRule) {
            $children = $currentRule->getChildren();
            assert(is_array($children));
            if (count($children) <= $nextRuleIndex) {
                return false;
            }
            if (!$currentRule->isTransitional()) {
                ++$this->depth;
            }
            $this->trace[] = new Entry($currentRule->getName(), $nextRuleIndex, $this->todo, $this->depth);
            $nextRule = $children[$nextRuleIndex];
            assert(is_string($nextRule) || is_int($nextRule));
            $this->todo[] = new ExitRule($nextRule, 0);
            $this->todo[] = new Entry($nextRule, 0);

            return true;
        } elseif ($currentRule instanceof RepetitionRule) {
            $nextRule = $currentRule->getChildren();

            if ($nextRuleIndex === 0) {
                $name = $currentRule->getName();
                $min = $currentRule->getMin();
                if (!$currentRule->isTransitional()) {
                    ++$this->depth;
                }
                $this->trace[] = new Entry($name, $min, [], $this->depth);
                array_pop($this->todo);
                $this->todo[] = new ExitRule($name, $min, $this->todo);
                for ($i = 0; $i < $min; ++$i) {
                    assert(is_string($nextRule) || is_int($nextRule));
                    $this->todo[] = new ExitRule($nextRule, 0);
                    $this->todo[] = new Entry($nextRule, 0);
                }
            } else {
                $max = $currentRule->getMax();
                if ($max != -1 && $max < $nextRuleIndex) {
                    return false;
                }
                $this->todo[] = new ExitRule($currentRule->getName(), $nextRuleIndex, $this->todo);
                assert(is_string($nextRule) || is_int($nextRule));
                $this->todo[] = new ExitRule($nextRule, 0);
                $this->todo[] = new Entry($nextRule, 0);
            }
            return true;
        }

        return false;
    }

    /**
     * Backtrack the trace.
     * @param Buffer<mixed,Token> $tokenSequence
     * @return bool
     */
    private function backtrack(Buffer $tokenSequence): bool
    {
        $found = false;

        do {
            $last = array_pop($this->trace);
            if ($last instanceof Entry) {
                $zeRule = $this->rules[$last->getRule()];
                $found = $zeRule instanceof ChoiceRule;
            } elseif ($last instanceof ExitRule) {
                $zeRule = $this->rules[$last->getRule()];
                $found = $zeRule instanceof RepetitionRule;
            } elseif ($last instanceof TokenRule) {
                $tokenSequence->previous();
                if (!$tokenSequence->valid()) {
                    return false;
                }
            }
        } while (0 < count($this->trace) && !$found);

        if (!$found) {
            return false;
        }

        assert($last instanceof InvocationRule);
        $rule = $last->getRule();
        $next = ((int) $last->getData()) + 1;
        $this->depth = $last->getDepth();
        $this->todo = $last->getTodo();
        $this->todo[] = new Entry($rule, $next);

        return true;
    }

    /**
     * Build AST from trace.
     * Walk through the trace iteratively and recursively.
     *
     * @param int $i Current trace index.
     * @param array &$children<TreeNode|array{id:string?,options:array}> Collected children.
     * @return TreeNode|int
     * @psalm-suppress ArgumentTypeCoercion
     * @psalm-suppress MixedArgumentTypeCoercion
     * @psalm-suppress MixedArgument
     * @psalm-suppress MixedAssignment
     */
    private function _buildTree(int $i = 0, array &$children = []): TreeNode|int
    {
        $max = count($this->trace);

        while ($i < $max) {
            assert(is_int($i));
            $trace = $this->trace[$i];

            if ($trace instanceof Entry) {
                $ruleName = $trace->getRule();
                $rule = $this->rules[$ruleName];
                $isRule = !$trace->isTransitional();
                $nextTrace = $this->trace[$i + 1];
                $id = $rule->getNodeId();

                // Optimization: Skip empty trace sequence.
                if ($nextTrace instanceof ExitRule && $ruleName == $nextTrace->getRule()) {
                    $i += 2;
                    continue;
                }

                if ($isRule) {
                    $children[] = $ruleName;
                }

                if (null !== $id) {
                    $children[] = [
                        'id' => $id,
                        'options' => $rule->getNodeOptions()
                    ];
                }

                $i = $this->_buildTree($i + 1, $children);

                if (!$isRule) {
                    continue;
                }

                $handle = [];
                $cId = null;
                $cOptions = [];

                do {
                    $pop = array_pop($children);

                    if (is_object($pop)) {
                        $handle[] = $pop;
                    } elseif (is_array($pop) && null === $cId) {
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

                if (in_array('M', $cOptions) && $this->mergeTree($children, $handle, $cId, false)) {
                    continue;
                }

                if (in_array('m', $cOptions) && $this->mergeTree($children, $handle, $cId, true)) {
                    continue;
                }

                $cTree = new TreeNode($id ?: $cId);

                foreach ($handle as $child) {
                    assert($child instanceof TreeNode);
                    $child->setParent($cTree);
                    $cTree->prependChild($child);
                }

                $children[] = $cTree;
            } elseif ($trace instanceof ExitRule) {
                return $i + 1;
            } elseif ($trace instanceof TokenRule) {
                if (!$trace->isKept()) {
                    $i++;
                    continue;
                }
                $child = new TreeNode('token', [
                    'token' => $trace->getTokenName(),
                    'value' => $trace->getValue(),
                    'namespace' => $trace->getNamespace(),
                ]);
                $children[] = $child;
                $i++;
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
     * @param array<TreeNode> $handle Children of the new node.
     * @param string $cId Node ID.
     * @param bool $recursive Whether we should merge recursively or not.
     * @return bool
     */
    private function mergeTree(array &$children, array $handle, string $cId, bool $recursive): bool
    {
        end($children);
        $last = current($children);
        if (!is_object($last)) {
            return false;
        }
        if ($cId !== $last->getId()) {
            return false;
        }
        if ($recursive) {
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
     * @return void
     */
    private function mergeTreeRecursive(TreeNode $node, TreeNode $newNode): void
    {
        $newNodeId = $newNode->getId();

        if ($newNodeId === 'token') {
            $node->appendChild($newNode);
            $newNode->setParent($node);
            return;
        }

        $children = $node->getChildren();
        end($children);
        $lastChild = current($children);

        if ($lastChild->getId() !== $newNodeId) {
            $node->appendChild($newNode);
            $newNode->setParent($node);
            return;
        }

        foreach ($newNode->getChildren() as $child) {
            $this->mergeTreeRecursive($lastChild, $child);
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
        return $this->pragmas;
    }

    /**
     * Get tokens.
     * @return array<string,array<string>>
     */
    public function getTokens(): array
    {
        return $this->tokens;
    }

    /**
     * Get rule by name
     */
    public function getRule(string|int $name): ?Rule
    {
        return $this->rules[$name] ?? null;
    }

    /**
     * Get rules.
     * @return array<Rule>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Get root rule.
     */
    public function getRootRule(): int|string
    {
        foreach ($this->rules as $rule => $_) {
            if (!is_int($rule)) {
                return $rule;
            }
        }
        throw new RuntimeException('Should never happen');
    }
}
