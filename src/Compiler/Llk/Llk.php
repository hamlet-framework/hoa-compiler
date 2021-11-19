<?php declare(strict_types=1);

namespace Hoa\Compiler\Llk;

use Hoa\Compiler;
use Hoa\Compiler\Exceptions\Exception;

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
        $pp = file_get_contents($path);
        if (empty($pp)) {
            $message = 'The grammar is empty';
            throw new Exception($message . '.', 0);
        }

        $tokens = [];
        $rawRules = [];
        $pragmas = [];
        static::parsePP($pp, $tokens, $rawRules, $pragmas, $path);

        $ruleAnalyzer = new Rules\Analyzer($tokens);
        $rules = $ruleAnalyzer->analyzeRules($rawRules);
        return new Parser($tokens, $rules, $pragmas);
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

        $escapeRuleName = function (string|int $ruleName) use ($parser): string|int {
            if (true == $parser->getRule($ruleName)?->isTransitional()) {
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

            if ($rule instanceof Rules\Token) {
                // Token name.
                $arguments['tokenName'] = '\'' . $rule->getTokenName() . '\'';
            } else {
                if ($rule instanceof Rules\Repetition) {
                    // Minimum.
                    $arguments['min'] = $rule->getMin();

                    // Maximum.
                    $arguments['max'] = $rule->getMax();
                }

                // Children.
                $ruleChildren = $rule->getChildren();

                if (null === $ruleChildren) {
                    $arguments['children'] = 'null';
                } elseif (false === is_array($ruleChildren)) {
                    $arguments['children'] = $escapeRuleName($ruleChildren);
                } else {
                    /**
                     * @psalm-suppress PossiblyInvalidArgument
                     */
                    $arguments['children'] =
                        '[' .
                        implode(', ', array_map($escapeRuleName, $ruleChildren)) .
                        ']';
                }
            }

            // Node ID.
            $nodeId = $rule->getNodeId();

            if (null === $nodeId) {
                $arguments['nodeId'] = 'null';
            } else {
                $arguments['nodeId'] = '\'' . $nodeId . '\'';
            }

            if ($rule instanceof Rules\Token) {
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

        $out =
            'class ' . $className . ' extends \Hoa\Compiler\Llk\Parser' . "\n" .
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

        return $out;
    }

    /**
     * Parse the grammar description language.
     *
     * @param string $pp Grammar description.
     * @param array<string,array<string,string>> $tokens Extracted tokens.
     * @param array<string,string> $rules Extracted raw rules.
     * @param array<string,string|int|bool> $pragmas Extracted raw pragmas.
     * @param string $streamName The name of the stream containing the grammar.
     * @return void
     * @throws Exception
     */
    public static function parsePP(string $pp, array &$tokens, array &$rules, array &$pragmas, string $streamName): void
    {
        $lines = explode("\n", $pp);
        $pragmas = [];
        $tokens = ['default' => []];
        $rules = [];

        for ($i = 0, $m = count($lines); $i < $m; ++$i) {
            $line = rtrim($lines[$i]);

            if (empty($line) || str_starts_with($line, '//')) {
                continue;
            }

            if ('%' == $line[0]) {
                if (0 !== preg_match('#^%pragma\h+([^\h]+)\h+(.*)$#u', $line, $matches)) {
                    switch ($matches[2]) {
                        case 'true':
                            $pragmaValue = true;

                            break;

                        case 'false':
                            $pragmaValue = false;

                            break;

                        default:
                            if (true === ctype_digit($matches[2])) {
                                $pragmaValue = intval($matches[2]);
                            } else {
                                $pragmaValue = $matches[2];
                            }
                    }

                    $pragmas[$matches[1]] = $pragmaValue;
                } elseif (0 !== preg_match('#^%skip\h+(?:([^:]+):)?([^\h]+)\h+(.*)$#u', $line, $matches)) {
                    if (empty($matches[1])) {
                        $matches[1] = 'default';
                    }

                    if (!isset($tokens[$matches[1]])) {
                        $tokens[$matches[1]] = [];
                    }

                    if (!isset($tokens[$matches[1]]['skip'])) {
                        $tokens[$matches[1]]['skip'] = $matches[3];
                    } else {
                        $tokens[$matches[1]]['skip'] =
                            '(?:' .
                            $tokens[$matches[1]]['skip'] . '|' .
                            $matches[3] .
                            ')';
                    }
                } elseif (0 !== preg_match('#^%token\h+(?:([^:]+):)?([^\h]+)\h+(.*?)(?:\h+->\h+(.*))?$#u', $line, $matches)) {
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
                        $streamName,
                        $i + 1
                    );
                    throw new Exception($message, 1);
                }

                continue;
            }

            $ruleName = substr($line, 0, -1);
            $rule = '';
            ++$i;
            while ($i < $m
                && isset($lines[$i][0])
                && (' ' === $lines[$i][0] || "\t" === $lines[$i][0] || str_starts_with($lines[$i], '//'))) {
                if (str_starts_with($lines[$i], '//')) {
                    ++$i;
                    continue;
                }
                $rule .= ' ' . trim($lines[$i++]);
            }
            if (isset($lines[$i][0])) {
                --$i;
            }

            $rules[$ruleName] = $rule;
        }
    }
}
