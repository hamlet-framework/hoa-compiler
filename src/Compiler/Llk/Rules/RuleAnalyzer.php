<?php declare(strict_types=1);

namespace Hamlet\Compiler\Llk\Rules;

use Hamlet\Compiler;
use Hamlet\Compiler\Exceptions\Exception;
use Hamlet\Compiler\Llk\Grammar;
use Hamlet\Compiler\Llk\Lexer;
use Hamlet\Compiler\Llk\Token;
use Hamlet\Iterator\Lookahead;

final class RuleAnalyzer
{
    /**
     * PP lexemes.
     * @var array{default:array<string,string>}
     */
    private static array $_ppLexemes = [
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
     * Rule name being analyzed.
     */
    private string|int|null $ruleName = null;

    /**
     * Parsed rules.
     * @var array<Rule>
     */
    protected array $parsedRules = [];

    /**
     * Counter to auto-name transitional rules.
     */
    private RuleCounter $ruleCounter;

    public function __construct(private Grammar $grammar)
    {
        $this->ruleCounter = new RuleCounter;
    }

    /**
     * Build the analyzer of the rules (does not analyze the rules).
     *
     * @return Rules
     * @throws Exception
     * @todo use Rules class
     */
    public function analyzeRawRules(): Rules
    {
        $rawRules = $this->grammar->rawRules();

        if (empty($rawRules)) {
            throw new Compiler\Exceptions\RuleException('No rules specified!', 0);
        }

        /**
         * @var array<int|string,Rule>
         */
        $this->parsedRules = [];
        $lexer = new Lexer();

        foreach ($rawRules as $ruleName => $ruleBody) {
            $ruleTokens = new Lookahead($lexer->run($ruleBody, RuleAnalyzer::$_ppLexemes));
            $ruleTokens->rewind();

            $this->ruleName = $ruleName;
            $nodeId = null;

            if (str_starts_with($ruleName, '#')) {
                $nodeId = $ruleName;
                $ruleName = substr($ruleName, 1);
            }

            $pNodeId = $nodeId;
            $rule = $this->rule($ruleTokens, $rawRules, $pNodeId);

            if ($rule === null) {
                $message = sprintf('Error while parsing rule %s.', $ruleName);
                throw new Exception($message, 1);
            }

            $zeRule = $this->parsedRules[$rule];
            $zeRule->setName($ruleName);
            $zeRule->setPPRepresentation($ruleBody);

            if ($nodeId !== null) {
                $zeRule->setDefaultId($nodeId);
            }

            unset($this->parsedRules[$rule]);
            $this->parsedRules[$ruleName] = $zeRule;
        }

        /**
         * @psalm-suppress MixedArgumentTypeCoercion
         */
        return new Rules($this->parsedRules);
    }

    /**
     * Implementation of “rule”.
     * @param Lookahead<mixed,Token> $ruleTokens
     * @param array<string,string> $rules
     * @param string|null $pNodeId
     * @return string|int|null
     */
    private function rule(Lookahead $ruleTokens, array $rules, ?string &$pNodeId): string|int|null
    {
        return $this->choice($ruleTokens, $rules, $pNodeId);
    }

    /**
     * Implementation of “choice”.
     * @param Lookahead<mixed,Token> $ruleTokens
     * @param array<string,string> $rawRules
     * @param ?string $pNodeId
     * @return string|int|null
     */
    private function choice(Lookahead $ruleTokens, array $rawRules, ?string &$pNodeId): string|int|null
    {
        $children = [];

        // concatenation() …
        $nNodeId = $pNodeId;
        $rule = $this->concatenation($ruleTokens, $rawRules, $nNodeId);

        if ($rule === null) {
            return null;
        }

        if ($nNodeId !== null) { // && $this->_parsedRules
            $this->parsedRules[$rule]->setNodeId($nNodeId);
        }

        $children[] = $rule;
        $others = false;

        // … ( ::or:: concatenation() )*
        while ($ruleTokens->current()->token === 'or') {
            $ruleTokens->next();
            $others = true;
            $nNodeId = $pNodeId;
            $rule = $this->concatenation($ruleTokens, $rawRules, $nNodeId);

            if ($rule === null) {
                return null;
            }

            if ($nNodeId !== null) {
                $this->parsedRules[$rule]->setNodeId($nNodeId);
            }

            $children[] = $rule;
        }

        $pNodeId = null;

        if (!$others) {
            return $rule;
        }

        $name = $this->ruleCounter->next();
        $this->parsedRules[$name] = new ChoiceRule($name, $children);

        return $name;
    }

    /**
     * Implementation of “concatenation”.
     * @param Lookahead<mixed,Token> $ruleTokens
     * @param array<string,string> $rawRules
     * @param string|null $pNodeId
     * @return string|int|null
     */
    private function concatenation(Lookahead $ruleTokens, array $rawRules, ?string &$pNodeId): string|int|null
    {
        $children = [];

        // repetition() …
        $rule = $this->repetition($ruleTokens, $rawRules, $pNodeId);

        if ($rule === null) {
            return null;
        }

        $children[] = $rule;
        $others = false;

        // … repetition()*
        while (($r1 = $this->repetition($ruleTokens, $rawRules, $pNodeId)) !== null) {
            $children[] = $r1;
            $others = true;
        }

        if (!$others && $pNodeId === null) {
            return $rule;
        }

        $name = $this->ruleCounter->next();
        $this->parsedRules[$name] = new ConcatenationRule($name, $children);

        return $name;
    }

    /**
     * Implementation of “repetition”.
     * @param Lookahead<mixed,Token> $ruleTokens
     * @param array<string,string> $rawRules
     * @param string|null $pNodeId
     * @return string|int|null
     */
    private function repetition(Lookahead $ruleTokens, array $rawRules, ?string &$pNodeId): string|int|null
    {
        // simple() …
        $children = $this->simple($ruleTokens, $rawRules, $pNodeId);

        if ($children === null) {
            return null;
        }

        // … quantifier()?
        $min = -1;
        $max = -1;
        switch ($ruleTokens->current()->token) {
            case 'zero_or_one':
                $min = 0;
                $max = 1;
                $ruleTokens->next();

                break;

            case 'one_or_more':
                $min = 1;
                $max = -1;
                $ruleTokens->next();

                break;

            case 'zero_or_more':
                $min = 0;
                $max = -1;
                $ruleTokens->next();

                break;

            case 'n_to_m':
                $handle = trim($ruleTokens->current()->value, '{}');
                $nm = explode(',', $handle);
                $min = (int)trim($nm[0]);
                $max = (int)trim($nm[1]);
                $ruleTokens->next();

                break;

            case 'zero_to_m':
                $min = 0;
                $max = (int)trim($ruleTokens->current()->value, '{,}');
                $ruleTokens->next();

                break;

            case 'n_or_more':
                $min = (int)trim($ruleTokens->current()->value, '{,}');
                $max = -1;
                $ruleTokens->next();

                break;

            case 'exactly_n':
                $handle = trim($ruleTokens->current()->value, '{}');
                $min = (int)$handle;
                $max = $min;
                $ruleTokens->next();

                break;
        }

        // … <node>?
        if ('node' === $ruleTokens->current()->token) {
            $pNodeId = $ruleTokens->current()->value;
            $ruleTokens->next();
        }

        if ($min == -1 && $max == -1) {
            return $children;
        }

        if (-1 != $max && $max < $min) {
            $message = sprintf('Upper bound %d must be greater or equal to lower bound %d in rule %s.', $max, $min, $this->ruleName ?? 'unknown');
            throw new Exception($message, 2);
        }

        $name = $this->ruleCounter->next();
        $this->parsedRules[$name] = new RepetitionRule($name, $min, $max, $children, null);

        return $name;
    }

    /**
     * Implementation of “simple”.
     * @param Lookahead<mixed,Token> $ruleTokens
     * @param array<string,string> $rawRules
     * @param string|null $pNodeId
     * @return int|string|null
     */
    private function simple(Lookahead $ruleTokens, array $rawRules, ?string &$pNodeId): string|int|null
    {
        if ($ruleTokens->current()->token === 'capturing_') {
            $ruleTokens->next();
            $rule = $this->choice($ruleTokens, $rawRules, $pNodeId);

            if ($rule === null) {
                return null;
            }

            if ($ruleTokens->current()->token != '_capturing') {
                return null;
            }

            $ruleTokens->next();
            return $rule;
        }

        if ($ruleTokens->current()->token === 'skipped') {
            $tokenName = trim($ruleTokens->current()->value, ':');
            [$tokenName, $unificationId, $exists] = $this->parseTokenName($tokenName);
            if (!$exists) {
                $message = sprintf('TokenRule ::%s:: does not exist in rule %s.', $tokenName, $this->ruleName ?? 'unknown');
                throw new Exception($message, 3);
            }

            $name = $this->ruleCounter->next();
            $tokenRule = new TokenRule((string)$name, $tokenName, null, $unificationId, false);
            $this->parsedRules[$name] = $tokenRule;
            $ruleTokens->next();

            return $name;
        }

        if ($ruleTokens->current()->token === 'kept') {
            $tokenName = trim($ruleTokens->current()->value, '<>');
            [$tokenName, $unificationId, $exists] = $this->parseTokenName($tokenName);
            if (!$exists) {
                $message = sprintf('TokenRule <%s> does not exist in rule %s.', $tokenName, $this->ruleName ?? 'unknown');
                throw new Exception($message, 4);
            }

            $name = $this->ruleCounter->next();
            $tokenRule = new TokenRule($name, $tokenName, null, $unificationId, true);
            $this->parsedRules[$name] = $tokenRule;
            $ruleTokens->next();

            return $name;
        }

        if ($ruleTokens->current()->token === 'named') {
            $tokenName = rtrim($ruleTokens->current()->value, '()');
            if (!array_key_exists($tokenName, $rawRules) && !array_key_exists('#' . $tokenName, $rawRules)) {
                $message = sprintf('Cannot call rule %s() in rule %s because it does not exist.', $tokenName, $this->ruleName ?? 'unknown');
                throw new Compiler\Exceptions\RuleException($message, 5);
            }

            if ($ruleTokens->key() === 0 && $ruleTokens->getNext()->token === 'EOF') {
                $name = $this->ruleCounter->next();
                $this->parsedRules[$name] = new ConcatenationRule($name, [$tokenName], null);
            } else {
                $name = $tokenName;
            }

            $ruleTokens->next();

            return $name;
        }

        return null;
    }

    /**
     * @param string $tokenName
     * @return array{string,int,bool}
     */
    private function parseTokenName(string $tokenName): array
    {
        if (str_ends_with($tokenName, ']')) {
            $openBracketPosition = strpos($tokenName, '[');
            if ($openBracketPosition === false) {
                $message = sprintf('Malformed token name "%s"', $tokenName);
                throw new Exception($message);
            }
            $tokenName = substr($tokenName, 0, $openBracketPosition);
            $unificationId = (int)substr($tokenName, $openBracketPosition + 1, -1);
        } else {
            $unificationId = -1;
        }
        $exists = false;
        foreach ($this->grammar->tokens() as $tokens) {
            foreach ($tokens as $token => $_) {
                if ($token == $tokenName || str_starts_with($token, $tokenName . ':')) {
                    $exists = true;
                    break 2;
                }
            }
        }
        return [$tokenName, $unificationId, $exists];
    }
}
