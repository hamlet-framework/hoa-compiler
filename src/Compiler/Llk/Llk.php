<?php declare(strict_types=1);

namespace Hamlet\Compiler\Llk;

use Hamlet\Compiler;
use Hamlet\Compiler\Exceptions\Exception;

abstract class Llk
{
    /**
     * Load in-memory parser from a grammar description file.
     * The grammar description language is PP. See
     * `hoa://Library/Compiler/Llk/Llk.pp` for an example, or the documentation.
     *
     * @param string $path Path to read the grammar from
     * @return Parser
     * @throws Exception
     */
    public static function load(string $path): Parser
    {
        $grammar = file_get_contents($path);
        if (empty($grammar)) {
            $message = sprintf('The grammar is empty at "%s"', $path);
            throw new Exception($message, 0);
        }

        $grammar = static::readGrammar($grammar, $path);

        $analyzer = new Rules\RuleAnalyzer($grammar);
        $rules = $analyzer->analyzeRawRules();
        return new Parser($grammar, $rules);
    }

    /**
     * Save in-memory parser to PHP code.
     * The generated PHP code will load the same in-memory parser. The state
     * will be reset. The parser will be saved as a class, named after
     * `$className`. To retrieve the parser, one must instantiate this class.
     *
     * @param Parser $parser Parser to save.
     * @param string $className Parser classname.
     * @return string
     * @psalm-suppress PossiblyNullOperand
     */
    public static function save(Parser $parser, string $className): string
    {
        $outTokens = null;
        $outRules = null;
        $outPragmas = null;
        $outExtra = null;

        $escapeRuleName = function (int|string $ruleName) use ($parser): string|int {
            if ($parser->getRule($ruleName)?->isTransitional()) {
                return $ruleName;
            }
            return '\'' . $ruleName . '\'';
        };

        foreach ($parser->getTokens() as $namespace => $tokens) {
            $outTokens .= '                \'' . $namespace . '\' => [' . "\n";

            foreach ($tokens as $tokenName => $tokenValue) {
                $outTokens .=
                    '                    \'' . $tokenName . '\' => \'' .
                    str_replace(
                        ['\'', '\\\\'],
                        ['\\\'', '\\\\\\'],
                        $tokenValue
                    ) . '\',' . "\n";
            }

            $outTokens .= '                ],' . "\n";
        }

        foreach ($parser->getRules() as $rule) {
            $arguments = [];

            // Name.
            $arguments['name'] = $escapeRuleName($rule->getName());

            if ($rule instanceof Rules\TokenRule) {
                // TokenRule name.
                $arguments['tokenName'] = '\'' . $rule->getTokenName() . '\'';
            } else {
                if ($rule instanceof Rules\RepetitionRule) {
                    // Minimum.
                    $arguments['min'] = $rule->getMin();

                    // Maximum.
                    $arguments['max'] = $rule->getMax();
                }

                // Children.
                $ruleChildren = $rule->getChildren();

                if (is_null($ruleChildren)) {
                    $arguments['children'] = 'null';
                } elseif (is_array($ruleChildren)) {
                    /**
                     * @psalm-suppress PossiblyInvalidArgument
                     */
                    $arguments['children'] = '[' . implode(', ', array_map($escapeRuleName, $ruleChildren)) . ']';
                } else {
                    $arguments['children'] = $escapeRuleName($ruleChildren);
                }
            }

            // Node ID.
            $nodeId = $rule->getNodeId();

            if (is_null($nodeId)) {
                $arguments['nodeId'] = 'null';
            } else {
                $arguments['nodeId'] = '\'' . $nodeId . '\'';
            }

            if ($rule instanceof Rules\TokenRule) {
                // Unification.
                $arguments['unification'] = $rule->getUnificationIndex();

                // Kept.
                $arguments['kept'] = $rule->isKept() ? 'true' : 'false';
            }

            // Default node ID.
            if (null !== $defaultNodeId = $rule->getDefaultId()) {
                $defaultNodeOptions = $rule->getDefaultOptions();

                if (!empty($defaultNodeOptions)) {
                    $defaultNodeId .= ':' . implode('', $defaultNodeOptions);
                }

                $outExtra .=
                    "\n" .
                    '        $this->getRule(' . $arguments['name'] . ')->setDefaultId(' .
                    '\'' . $defaultNodeId . '\'' .
                    ');';
            }

            // PP representation.
            if (null !== $ppRepresentation = $rule->getPPRepresentation()) {
                $outExtra .=
                    "\n" .
                    '        $this->getRule(' . $arguments['name'] . ')->setPPRepresentation(' .
                    '\'' . str_replace('\'', '\\\'', $ppRepresentation) . '\'' .
                    ');';
            }

            $outRules .=
                "\n" .
                '                ' . $arguments['name'] . ' => new \\' . get_class($rule) . '(' .
                implode(', ', $arguments) .
                '),';
        }

        foreach ($parser->getPragmas() as $pragmaName => $pragmaValue) {
            $outPragmas .=
                "\n" .
                '                \'' . $pragmaName . '\' => ' .
                (is_bool($pragmaValue)
                    ? (true === $pragmaValue ? 'true' : 'false')
                    : (is_int($pragmaValue)
                        ? $pragmaValue
                        : '\'' . $pragmaValue . '\'')) .
                ',';
        }

        return
            'class ' . $className . ' extends \Hamlet\Compiler\Llk\Parser' . "\n" .
            '{' . "\n" .
            '    public function __construct()' . "\n" .
            '    {' . "\n" .
            '        parent::__construct(' . "\n" .
            '            [' . "\n" .
            $outTokens .
            '            ],' . "\n" .
            '            [' .
            $outRules . "\n" .
            '            ],' . "\n" .
            '            [' .
            $outPragmas . "\n" .
            '            ]' . "\n" .
            '        );' . "\n" .
            $outExtra . "\n" .
            '    }' . "\n" .
            '}' . "\n";
    }

    /**
     * Parse the grammar description language.
     * Extract grammar reader into a separate file
     *
     * @param string $grammarDescription Grammar description.
     * @param string $grammarFileName The name of the stream containing the grammar.
     * @return Grammar
     * @throws Exception
     */
    public static function readGrammar(string $grammarDescription, string $grammarFileName): Grammar
    {
        $lines = explode("\n", $grammarDescription);
        $pragmas = [];
        $tokens = ['default' => []];
        $rawRules = [];

        for ($i = 0, $m = count($lines); $i < $m; $i++) {
            $line = rtrim($lines[$i]);

            // Skip comments
            if (empty($line) || str_starts_with($line, '//')) {
                continue;
            }

            // Parse instructions: pragmas, skips and tokens
            if (str_starts_with($line, '%')) {
                if (preg_match('#^%pragma\h+(\H+)\h+(.*)$#u', $line, $matches) !== 0) {
                    switch ($matches[2]) {
                        case 'true':
                            $pragmaValue = true;
                            break;
                        case 'false':
                            $pragmaValue = false;
                            break;
                        default:
                            if (ctype_digit($matches[2])) {
                                $pragmaValue = intval($matches[2]);
                            } else {
                                $pragmaValue = $matches[2];
                            }
                    }
                    $pragmas[$matches[1]] = $pragmaValue;
                } elseif (preg_match('#^%skip\h+(?:([^:]+):)?(\H+)\h+(.*)$#u', $line, $matches) !== 0) {
                    if (empty($matches[1])) {
                        $matches[1] = 'default';
                    }
                    if (!isset($tokens[$matches[1]])) {
                        $tokens[$matches[1]] = [];
                    }
                    if (!isset($tokens[$matches[1]]['skip'])) {
                        $tokens[$matches[1]]['skip'] = $matches[3];
                    } else {
                        $tokens[$matches[1]]['skip'] = '(?:' . $tokens[$matches[1]]['skip'] . '|' . $matches[3] . ')';
                    }
                } elseif (preg_match('#^%token\h+(?:([^:]+):)?(\H+)\h+(.*?)(?:\h+->\h+(.*))?$#u', $line, $matches) !== 0) {
                    if (empty($matches[1])) {
                        $matches[1] = 'default';
                    }
                    if (isset($matches[4]) && !empty($matches[4])) {
                        $matches[2] = $matches[2] . ':' . $matches[4];
                    }
                    if (!isset($tokens[$matches[1]])) {
                        $tokens[$matches[1]] = [];
                    }
                    $tokens[$matches[1]][$matches[2]] = $matches[3];
                } else {
                    $message = sprintf(
                        'Unrecognized instructions:' . "\n" .
                        '    %s' . "\n" . 'in file %s at line %d.',
                        $line,
                        $grammarFileName,
                        $i + 1
                    );
                    throw new Exception($message, 1);
                }
                continue;
            }

            $ruleName = substr($line, 0, -1);
            $rule = '';
            $i++;
            while ($i < $m
                && strlen($lines[$i]) > 0
                && (str_starts_with($lines[$i], ' ') || str_starts_with($lines[$i], "\t") || str_starts_with($lines[$i], '//'))) {
                if (str_starts_with($lines[$i], '//')) {
                    // Skip comments inside of rules
                    $i++;
                    continue;
                }
                $rule .= ' ' . trim($lines[$i++]);
            }
            if (isset($lines[$i][0])) {
                $i--;
            }
            $rawRules[$ruleName] = $rule;
        }

        return new Grammar($tokens, $rawRules, $pragmas);
    }
}
