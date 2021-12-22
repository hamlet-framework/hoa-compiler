<?php declare(strict_types=1);

namespace Hoa\Compiler\Llk\Rules;

use Hoa\Compiler;
use Hoa\Compiler\Exceptions\Exception;
use Hoa\Compiler\Llk\Lexer;
use Hoa\Iterator\Lookahead;

final class AnalyzerRule
{
    /**
     * PP lexemes.
     * @var array{default:array<string,string>}
     */
    protected static array $_ppLexemes = [
        'default' => [
            'skip'         => '\s',
            'or'           => '\|',
            'zero_or_one'  => '\?',
            'one_or_more'  => '\+',
            'zero_or_more' => '\*',
            'n_to_m'       => '\{[0-9]+,[0-9]+\}',
            'zero_to_m'    => '\{,[0-9]+\}',
            'n_or_more'    => '\{[0-9]+,\}',
            'exactly_n'    => '\{[0-9]+\}',
            'skipped'      => '::[a-zA-Z_][a-zA-Z0-9_]*(\[\d+\])?::',
            'kept'         => '<[a-zA-Z_][a-zA-Z0-9_]*(\[\d+\])?>',
            'named'        => '[a-zA-Z_][a-zA-Z0-9_]*\(\)',
            'node'         => '#[a-zA-Z_][a-zA-Z0-9_]*(:[mM])?',
            'capturing_'   => '\(',
            '_capturing'   => '\)',
        ]
    ];

    /**
     * Lexer iterator.
     * @var ?Lookahead<mixed,\Hoa\Compiler\Llk\Token>
     */
    protected ?Lookahead $lexer = null;

    /**
     * Rules.
     * @var array<string,string>
     */
    protected ?array $_rules = null;

    /**
     * RuleException name being analyzed.
     */
    private string|int|null $_ruleName = null;

    /**
     * Parsed rules.
     * @var array<Rule>
     */
    protected ?array $_parsedRules = null;

    /**
     * Counter to auto-name transitional rules.
     */
    protected int $_transitionalRuleCounter = 0;

    /**
     * @param array<string,array<string,string>> $tokens Tokens representing rules.
     */
    public function __construct(private array $tokens)
    {
    }

    /**
     * Build the analyzer of the rules (does not analyze the rules).
     *
     * @param array<string,string> $rules Rules to be analyzed.
     * @return array<Rule>
     * @throws Exception
     */
    public function analyzeRules(array $rules): array
    {
        if (empty($rules)) {
            throw new Compiler\Exceptions\RuleException('No rules specified!', 0);
        }

        /**
         * @var array<Rule>
         */
        $this->_parsedRules = [];
        $this->_rules = $rules;
        $lexer = new Lexer();

        foreach ($rules as $key => $value) {
            $this->lexer = new Lookahead($lexer->lexMe($value, AnalyzerRule::$_ppLexemes));
            $this->lexer->rewind();

            $this->_ruleName = $key;
            $nodeId = null;

            if (str_starts_with($key, '#')) {
                $nodeId = $key;
                $key = substr($key, 1);
            }

            $pNodeId = $nodeId;
            $rule = $this->rule($pNodeId);

            if (null === $rule) {
                $message = sprintf('Error while parsing rule %s.', $key);
                throw new Exception($message, 1);
            }

            assert(!empty($this->_parsedRules));

            $zeRule = $this->_parsedRules[$rule];
            $zeRule->setName($key);
            $zeRule->setPPRepresentation($value);

            if (null !== $nodeId) {
                $zeRule->setDefaultId($nodeId);
            }

            unset($this->_parsedRules[$rule]);
            $this->_parsedRules[$key] = $zeRule;
        }

        return $this->_parsedRules;
    }

    /**
     * Implementation of “rule”.
     */
    protected function rule(?string &$pNodeId): string|int|null
    {
        return $this->choice($pNodeId);
    }

    /**
     * Implementation of “choice”.
     * @psalm-suppress PossiblyNullReference
     * @psalm-suppress PossiblyNullArrayAccess
     */
    protected function choice(?string &$pNodeId): string|int|null
    {
        $children = [];

        // concatenation() …
        $nNodeId = $pNodeId;
        $rule = $this->concatenation($nNodeId);

        if ($rule === null) {
            return null;
        }

        if ($nNodeId !== null) { // && $this->_parsedRules
            $this->_parsedRules[$rule]->setNodeId($nNodeId);
        }

        $children[] = $rule;
        $others = false;

        // … ( ::or:: concatenation() )*
        while ($this->lexer->current()->token === 'or') {
            $this->lexer->next();
            $others = true;
            $nNodeId = $pNodeId;
            $rule = $this->concatenation($nNodeId);

            if ($rule === null) {
                return null;
            }

            if ($nNodeId !== null) {
                $this->_parsedRules[$rule]->setNodeId($nNodeId);
            }

            $children[] = $rule;
        }

        $pNodeId = null;

        if ($others === false) {
            return $rule;
        }

        $name = $this->_transitionalRuleCounter++;
        $this->_parsedRules[$name] = new ChoiceRule($name, $children);

        return $name;
    }

    /**
     * Implementation of “concatenation”.
     */
    protected function concatenation(?string &$pNodeId): string|int|null
    {
        $children = [];

        // repetition() …
        $rule = $this->repetition($pNodeId);

        if ($rule === null) {
            return null;
        }

        $children[] = $rule;
        $others = false;

        // … repetition()*
        while (($r1 = $this->repetition($pNodeId)) !== null) {
            $children[] = $r1;
            $others = true;
        }

        if (false === $others && null === $pNodeId) {
            return $rule;
        }

        $name = $this->_transitionalRuleCounter++;
        $this->_parsedRules[$name] = new ConcatenationRule($name, $children);

        return $name;
    }

    /**
     * Implementation of “repetition”.
     * @throws Exception
     * @psalm-suppress PossiblyNullReference
     * @psalm-suppress UnusedVariable
     */
    protected function repetition(?string &$pNodeId): string|int|null
    {
        // simple() …
        $children = $this->simple($pNodeId);

        if (null === $children) {
            return null;
        }

        // … quantifier()?
        switch ($this->lexer->current()->token) {
            case 'zero_or_one':
                $min = 0;
                $max = 1;
                $this->lexer->next();

                break;

            case 'one_or_more':
                $min = 1;
                $max = -1;
                $this->lexer->next();

                break;

            case 'zero_or_more':
                $min = 0;
                $max = -1;
                $this->lexer->next();

                break;

            case 'n_to_m':
                $handle = trim($this->lexer->current()->value, '{}');
                $nm = explode(',', $handle);
                $min = (int)trim($nm[0]);
                $max = (int)trim($nm[1]);
                $this->lexer->next();

                break;

            case 'zero_to_m':
                $min = 0;
                $max = (int)trim($this->lexer->current()->value, '{,}');
                $this->lexer->next();

                break;

            case 'n_or_more':
                $min = (int)trim($this->lexer->current()->value, '{,}');
                $max = -1;
                $this->lexer->next();

                break;

            case 'exactly_n':
                $handle = trim($this->lexer->current()->value, '{}');
                $min = (int)$handle;
                $max = $min;
                $this->lexer->next();

                break;
        }

        // … <node>?
        if ('node' === $this->lexer->current()->token) {
            $pNodeId = $this->lexer->current()->value;
            $this->lexer->next();
        }

        if (!isset($min)) {
            return $children;
        }

        /**
         * @var int $min
         * @var int $max
         */

        if (-1 != $max && $max < $min) {
            $message = sprintf('Upper bound %d must be greater or equal to lower bound %d in rule %s.', $max, $min, $this->_ruleName ?? 'unknown');
            throw new Exception($message, 2);
        }

        $name = $this->_transitionalRuleCounter++;
        $this->_parsedRules[$name] = new RepetitionRule(
            $name,
            $min,
            $max,
            $children,
            null
        );

        return $name;
    }

    /**
     * Implementation of “simple”.
     * @psalm-suppress PossiblyFalseArgument
     * @psalm-suppress PossiblyFalseOperand
     * @psalm-suppress PossiblyNullReference
     * @throws Exception
     */
    protected function simple(?string &$pNodeId): string|int|null
    {
        if ('capturing_' === $this->lexer->current()->token) {
            $this->lexer->next();
            $rule = $this->choice($pNodeId);

            if (null === $rule) {
                return null;
            }

            if ('_capturing' != $this->lexer->current()->token) {
                return null;
            }

            $this->lexer->next();

            return $rule;
        }

        if ('skipped' === $this->lexer->current()->token) {
            $tokenName = trim($this->lexer->current()->value, ':');

            if (str_ends_with($tokenName, ']')) {
                $uId = (int)substr($tokenName, strpos($tokenName, '[') + 1, -1);
                $tokenName = substr($tokenName, 0, strpos($tokenName, '['));
            } else {
                $uId = -1;
            }

            $exists = false;

            foreach ($this->tokens as $tokens) {
                foreach ($tokens as $token => $_) {
                    if ($token === $tokenName ||
                        str_contains($token, ':') && substr($token, 0, strpos($token, ':')) === $tokenName) {
                        $exists = true;
                        break 2;
                    }
                }
            }

            if (false == $exists) {
                $message = sprintf('TokenRule ::%s:: does not exist in rule %s.', $tokenName, $this->_ruleName ?? 'unknown');
                throw new Exception($message, 3);
            }

            $name = $this->_transitionalRuleCounter++;
            $this->_parsedRules[$name] = new TokenRule((string)$name, $tokenName, null, $uId, false);
            $this->lexer->next();

            return $name;
        }

        if ('kept' === $this->lexer->current()->token) {
            $tokenName = trim($this->lexer->current()->value, '<>');

            if (str_ends_with($tokenName, ']')) {
                $uId = (int)substr($tokenName, strpos($tokenName, '[') + 1, -1);
                $tokenName = substr($tokenName, 0, strpos($tokenName, '['));
            } else {
                $uId = -1;
            }

            $exists = false;

            foreach ($this->tokens as $tokens) {
                foreach ($tokens as $token => $_) {
                    if ($token === $tokenName ||
                        str_contains($token, ':') && substr($token, 0, strpos($token, ':')) === $tokenName) {
                        $exists = true;
                        break 2;
                    }
                }
            }

            if (false == $exists) {
                $message = sprintf('TokenRule <%s> does not exist in rule %s.', $tokenName, $this->_ruleName ?? 'unknown');
                throw new Exception($message, 4);
            }

            $name = $this->_transitionalRuleCounter++;
            $token = new TokenRule(
                $name,
                $tokenName,
                null,
                $uId,
                true
            );
            $this->_parsedRules[$name] = $token;
            $this->lexer->next();

            return $name;
        }

        if ('named' === $this->lexer->current()->token) {
            $tokenName = rtrim($this->lexer->current()->value, '()');

            assert($this->_rules !== null);

            if (false === array_key_exists($tokenName, $this->_rules) &&
                false === array_key_exists('#' . $tokenName, $this->_rules)) {
                $message = sprintf('Cannot call rule %s() in rule %s because it does not exist.', $tokenName, $this->_ruleName ?? 'unknown');
                throw new Compiler\Exceptions\RuleException($message, 5);
            }

            if (0 === $this->lexer->key() &&
                'EOF' === $this->lexer->getNext()->token) {
                $name = $this->_transitionalRuleCounter++;
                $this->_parsedRules[$name] = new ConcatenationRule(
                    $name,
                    [$tokenName],
                    null
                );
            } else {
                $name = $tokenName;
            }

            $this->lexer->next();

            return $name;
        }

        return null;
    }
}
