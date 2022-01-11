<?php declare(strict_types=1);

namespace Hamlet\Compiler\Llk\Rules;

use Hamlet\Compiler;
use Hamlet\Compiler\Exceptions\Exception;
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

    /**
     * @param array<string,array<string,string>> $tokens Tokens representing rules.
     */
    public function __construct(private array $tokens)
    {
        $this->ruleCounter = new RuleCounter;
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
        $this->parsedRules = [];
        $lexer = new Lexer();

        foreach ($rules as $key => $value) {
            $lookahead = new Lookahead($lexer->run($value, RuleAnalyzer::$_ppLexemes));
            $lookahead->rewind();

            $this->ruleName = $key;
            $nodeId = null;

            if (str_starts_with($key, '#')) {
                $nodeId = $key;
                $key = substr($key, 1);
            }

            $pNodeId = $nodeId;
            $rule = $this->rule($lookahead, $rules, $pNodeId);

            if ($rule === null) {
                $message = sprintf('Error while parsing rule %s.', $key);
                throw new Exception($message, 1);
            }

            $zeRule = $this->parsedRules[$rule];
            $zeRule->setName($key);
            $zeRule->setPPRepresentation($value);

            if ($nodeId !== null) {
                $zeRule->setDefaultId($nodeId);
            }

            unset($this->parsedRules[$rule]);
            $this->parsedRules[$key] = $zeRule;
        }

        return $this->parsedRules;
    }

    /**
     * Implementation of “rule”.
     * @param Lookahead<mixed,Token> $lookahead
     * @param array<string,string> $rules
     * @param string|null $pNodeId
     * @return string|int|null
     */
    private function rule(Lookahead $lookahead, array $rules, ?string &$pNodeId): string|int|null
    {
        return $this->choice($lookahead, $rules, $pNodeId);
    }

    /**
     * Implementation of “choice”.
     * @param Lookahead<mixed,Token> $lookahead
     * @param array<string,string> $rules
     * @param ?string $pNodeId
     * @return string|int|null
     */
    private function choice(Lookahead $lookahead, array $rules, ?string &$pNodeId): string|int|null
    {
        $children = [];

        // concatenation() …
        $nNodeId = $pNodeId;
        $rule = $this->concatenation($lookahead, $rules, $nNodeId);

        if ($rule === null) {
            return null;
        }

        if ($nNodeId !== null) { // && $this->_parsedRules
            $this->parsedRules[$rule]->setNodeId($nNodeId);
        }

        $children[] = $rule;
        $others = false;

        // … ( ::or:: concatenation() )*
        while ($lookahead->current()->token === 'or') {
            $lookahead->next();
            $others = true;
            $nNodeId = $pNodeId;
            $rule = $this->concatenation($lookahead, $rules, $nNodeId);

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
     * @param Lookahead<mixed,Token> $lookahead
     * @param array<string,string> $rules
     * @param string|null $pNodeId
     * @return string|int|null
     */
    private function concatenation(Lookahead $lookahead, array $rules, ?string &$pNodeId): string|int|null
    {
        $children = [];

        // repetition() …
        $rule = $this->repetition($lookahead, $rules, $pNodeId);

        if ($rule === null) {
            return null;
        }

        $children[] = $rule;
        $others = false;

        // … repetition()*
        while (($r1 = $this->repetition($lookahead, $rules, $pNodeId)) !== null) {
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
     * @param Lookahead<mixed,Token> $lookahead
     * @param array<string,string> $rules
     * @param string|null $pNodeId
     * @return string|int|null
     */
    private function repetition(Lookahead $lookahead, array $rules, ?string &$pNodeId): string|int|null
    {
        // simple() …
        $children = $this->simple($lookahead, $rules, $pNodeId);

        if ($children === null) {
            return null;
        }

        // … quantifier()?
        $min = -1;
        $max = -1;
        switch ($lookahead->current()->token) {
            case 'zero_or_one':
                $min = 0;
                $max = 1;
                $lookahead->next();

                break;

            case 'one_or_more':
                $min = 1;
                $max = -1;
                $lookahead->next();

                break;

            case 'zero_or_more':
                $min = 0;
                $max = -1;
                $lookahead->next();

                break;

            case 'n_to_m':
                $handle = trim($lookahead->current()->value, '{}');
                $nm = explode(',', $handle);
                $min = (int)trim($nm[0]);
                $max = (int)trim($nm[1]);
                $lookahead->next();

                break;

            case 'zero_to_m':
                $min = 0;
                $max = (int)trim($lookahead->current()->value, '{,}');
                $lookahead->next();

                break;

            case 'n_or_more':
                $min = (int)trim($lookahead->current()->value, '{,}');
                $max = -1;
                $lookahead->next();

                break;

            case 'exactly_n':
                $handle = trim($lookahead->current()->value, '{}');
                $min = (int)$handle;
                $max = $min;
                $lookahead->next();

                break;
        }

        // … <node>?
        if ('node' === $lookahead->current()->token) {
            $pNodeId = $lookahead->current()->value;
            $lookahead->next();
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
     * @param Lookahead<mixed,Token> $lookahead
     * @param array<string,string> $rules
     * @param string|null $pNodeId
     * @return int|string|null
     */
    private function simple(Lookahead $lookahead, array $rules, ?string &$pNodeId): string|int|null
    {
        if ($lookahead->current()->token === 'capturing_') {
            $lookahead->next();
            $rule = $this->choice($lookahead, $rules, $pNodeId);

            if ($rule === null) {
                return null;
            }

            if ($lookahead->current()->token != '_capturing') {
                return null;
            }

            $lookahead->next();
            return $rule;
        }

        if ($lookahead->current()->token === 'skipped') {
            $tokenName = trim($lookahead->current()->value, ':');
            [$tokenName, $unificationId, $exists] = $this->parseTokenName($tokenName);
            if (!$exists) {
                $message = sprintf('TokenRule ::%s:: does not exist in rule %s.', $tokenName, $this->ruleName ?? 'unknown');
                throw new Exception($message, 3);
            }

            $name = $this->ruleCounter->next();
            $tokenRule = new TokenRule((string)$name, $tokenName, null, $unificationId, false);
            $this->parsedRules[$name] = $tokenRule;
            $lookahead->next();

            return $name;
        }

        if ($lookahead->current()->token === 'kept') {
            $tokenName = trim($lookahead->current()->value, '<>');
            [$tokenName, $unificationId, $exists] = $this->parseTokenName($tokenName);
            if (!$exists) {
                $message = sprintf('TokenRule <%s> does not exist in rule %s.', $tokenName, $this->ruleName ?? 'unknown');
                throw new Exception($message, 4);
            }

            $name = $this->ruleCounter->next();
            $tokenRule = new TokenRule($name, $tokenName, null, $unificationId, true);
            $this->parsedRules[$name] = $tokenRule;
            $lookahead->next();

            return $name;
        }

        if ($lookahead->current()->token === 'named') {
            $tokenName = rtrim($lookahead->current()->value, '()');
            if (!array_key_exists($tokenName, $rules) && !array_key_exists('#' . $tokenName, $rules)) {
                $message = sprintf('Cannot call rule %s() in rule %s because it does not exist.', $tokenName, $this->ruleName ?? 'unknown');
                throw new Compiler\Exceptions\RuleException($message, 5);
            }

            if ($lookahead->key() === 0 && $lookahead->getNext()->token === 'EOF') {
                $name = $this->ruleCounter->next();
                $this->parsedRules[$name] = new ConcatenationRule($name, [$tokenName], null);
            } else {
                $name = $tokenName;
            }

            $lookahead->next();

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
        foreach ($this->tokens as $tokens) {
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