<?php

namespace Hamlet\Compiler\Llk;

use Hamlet\Compiler\Exceptions\Exception;
use Hamlet\Compiler\Llk\Rules\TokenRule;
use PHPUnit\Framework\TestCase;

class LlkTest extends TestCase
{
    public function test_parse_skip_tokens(): void
    {
        $pp = '%skip  foobar1            bazqux1' . "\n" .
              '%skip  foobar2            bazqux2' . "\n" .
              '%skip  foobar3            bazqux3' . "\n" .
              '%skip  sourceNS1:foobar4  bazqux4' . "\n" .
              '%skip  sourceNS1:foobar5  bazqux5' . "\n" .
              '%skip  sourceNS2:foobar6  bazqux6' . "\n";

        $grammar = Llk::readGrammar($pp, 'streamFoo');

        $this->assertEquals([
            'default' => [
                'skip' => '(?:(?:bazqux1|bazqux2)|bazqux3)',
            ],
            'sourceNS1' => [
                'skip' => '(?:bazqux4|bazqux5)'
            ],
            'sourceNS2' => [
                'skip' => 'bazqux6'
            ]
        ], $grammar->tokens());
        $this->assertEmpty($grammar->rawRules());
        $this->assertEmpty($grammar->pragmas());
    }

    public function test_parse_tokens(): void
    {
        $pp = '%token  foobar1            bazqux1' . "\n" .
              '%token  sourceNS1:foobar2  bazqux2' . "\n" .
              '%token  sourceNS2:foobar3  bazqux3  -> destinationNS' . "\n" .
              '%token  foobar4            barqux4  -> destinationNS';
        $grammar = Llk::readGrammar($pp, 'streamFoo');

        $this->assertEquals([
            'default' => [
                'foobar1'               => 'bazqux1',
                'foobar4:destinationNS' => 'barqux4'
            ],
            'sourceNS1' => [
                'foobar2' => 'bazqux2'
            ],
            'sourceNS2' => [
                'foobar3:destinationNS' => 'bazqux3'
            ]
        ], $grammar->tokens());
        $this->assertEmpty($grammar->rawRules());
        $this->assertEmpty($grammar->pragmas());
    }

    public function test_parse_pragmas(): void
    {
        $pp = '%pragma  truly   true' . "\n" .
              '%pragma  falsy   false' . "\n" .
              '%pragma  numby   42' . "\n" .
              '%pragma  foobar  hello' . "\n" .
              '%pragma  bazqux  "world!"  ' . "\n";
        $grammar = Llk::readGrammar($pp, 'streamFoo');

        $this->assertEquals(['default' => []], $grammar->tokens());
        $this->assertEmpty($grammar->rawRules());
        $this->assertEquals([
            'truly'  => true,
            'falsy'  => false,
            'numby'  => 42,
            'foobar' => 'hello',
            'bazqux' => '"world!"'
        ], $grammar->pragmas());
    }

    public function test_unrecognized_instructions(): void
    {
        $pp = '// shift line' . "\n" .
              '%foobar baz qux' . "\n";

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'Unrecognized instructions:' . "\n" .
            '    %foobar baz qux' . "\n" .
            'in file streamFoo at line 2.'
        );
        Llk::readGrammar($pp, 'streamFoo');
    }

    public function test_parse_rules(): void
    {
        $pp = 'ruleA:' . "\n" .
              ' single space' . "\n" .
              ' single space' . "\n" .
              'ruleB:' . "\n" .
              '    many spaces' . "\n" .
              "\t" . 'single tab' . "\n" .
              'ruleC:' . "\n" .
              "\t\t" . 'many tabs' . "\n";
        $grammar = Llk::readGrammar($pp, 'streamFoo');

        $this->assertEquals(['default' => []], $grammar->tokens());
        $this->assertEquals([
            'ruleA' => ' single space single space',
            'ruleB' => ' many spaces single tab',
            'ruleC' => ' many tabs'
        ], $grammar->rawRules());
        $this->assertEmpty($grammar->pragmas());
    }

    public function test_parse_skip_comments(): void
    {
        $pp = '// Hello,' . "\n" .
              '//   World!';
        $grammar = Llk::readGrammar($pp, 'streamFoo');

        $this->assertEquals(['default' => []], $grammar->tokens());
        $this->assertEmpty($grammar->rawRules());
        $this->assertEmpty($grammar->pragmas());
    }

    public function test_load_empty(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The grammar is empty at');
        Llk::load(__DIR__ . '/../../Grammars/Empty.pp');
    }

    public function test_load(): void
    {
        $path = __DIR__ . '/../../Grammars/Simple.pp';
        $ruleA = new TokenRule('ruleA', 'foobar', null, -1, true);
        $ruleA->setPPRepresentation(' <foobar>');

        $parser = Llk::load($path);
        $this->assertEquals([
            'hello' => 'world'
        ], $parser->getPragmas());
        $this->assertEquals([
            'default' => [
                'foobar' => 'bazqux'
            ]
        ], $parser->getTokens());
        $this->assertEquals([
            'ruleA' => $ruleA
        ], iterator_to_array($parser->getRules()));
    }

    public function test_save()
    {
        $path = __DIR__ . '/../../../src/Grammars/Llk.pp';
        $parser = Llk::load($path);

        $code = <<<'CODE'
class Foobar extends \Hamlet\Compiler\Llk\Parser
{
    public function __construct()
    {
        parent::__construct(
            [
                'default' => [
                    'skip' => '\s',
                    'or' => '\|',
                    'zero_or_one' => '\?',
                    'one_or_more' => '\+',
                    'zero_or_more' => '\*',
                    'n_to_m' => '\{[0-9]+,[0-9]+\}',
                    'zero_to_m' => '\{,[0-9]+\}',
                    'n_or_more' => '\{[0-9]+,\}',
                    'exactly_n' => '\{[0-9]+\}',
                    'token' => '[a-zA-Z_][a-zA-Z0-9_]*',
                    'skipped' => '::',
                    'kept_' => '<',
                    '_kept' => '>',
                    'named' => '\(\)',
                    'node' => '#[a-zA-Z_][a-zA-Z0-9_]*(:[mM])?',
                    'capturing_' => '\(',
                    '_capturing' => '\)',
                    'unification_' => '\[',
                    'unification' => '[0-9]+',
                    '_unification' => '\]',
                ],
            ],
            [
                0 => new \Hamlet\Compiler\Llk\Rules\ConcatenationRule(0, ['choice'], null),
                'rule' => new \Hamlet\Compiler\Llk\Rules\ConcatenationRule('rule', [0], '#rule'),
                2 => new \Hamlet\Compiler\Llk\Rules\TokenRule(2, 'or', null, -1, false),
                3 => new \Hamlet\Compiler\Llk\Rules\ConcatenationRule(3, [2, 'concatenation'], '#choice'),
                4 => new \Hamlet\Compiler\Llk\Rules\RepetitionRule(4, 0, -1, 3, null),
                'choice' => new \Hamlet\Compiler\Llk\Rules\ConcatenationRule('choice', ['concatenation', 4], null),
                6 => new \Hamlet\Compiler\Llk\Rules\ConcatenationRule(6, ['repetition'], '#concatenation'),
                7 => new \Hamlet\Compiler\Llk\Rules\RepetitionRule(7, 0, -1, 6, null),
                'concatenation' => new \Hamlet\Compiler\Llk\Rules\ConcatenationRule('concatenation', ['repetition', 7], null),
                9 => new \Hamlet\Compiler\Llk\Rules\ConcatenationRule(9, ['quantifier'], '#repetition'),
                10 => new \Hamlet\Compiler\Llk\Rules\RepetitionRule(10, 0, 1, 9, null),
                11 => new \Hamlet\Compiler\Llk\Rules\TokenRule(11, 'node', null, -1, true),
                12 => new \Hamlet\Compiler\Llk\Rules\RepetitionRule(12, 0, 1, 11, null),
                'repetition' => new \Hamlet\Compiler\Llk\Rules\ConcatenationRule('repetition', ['simple', 10, 12], null),
                14 => new \Hamlet\Compiler\Llk\Rules\TokenRule(14, 'capturing_', null, -1, false),
                15 => new \Hamlet\Compiler\Llk\Rules\TokenRule(15, '_capturing', null, -1, false),
                16 => new \Hamlet\Compiler\Llk\Rules\ConcatenationRule(16, [14, 'choice', 15], null),
                17 => new \Hamlet\Compiler\Llk\Rules\TokenRule(17, 'skipped', null, -1, false),
                18 => new \Hamlet\Compiler\Llk\Rules\TokenRule(18, 'token', null, -1, true),
                19 => new \Hamlet\Compiler\Llk\Rules\TokenRule(19, 'unification_', null, -1, false),
                20 => new \Hamlet\Compiler\Llk\Rules\TokenRule(20, 'unification', null, -1, true),
                21 => new \Hamlet\Compiler\Llk\Rules\TokenRule(21, '_unification', null, -1, false),
                22 => new \Hamlet\Compiler\Llk\Rules\ConcatenationRule(22, [19, 20, 21], null),
                23 => new \Hamlet\Compiler\Llk\Rules\RepetitionRule(23, 0, 1, 22, null),
                24 => new \Hamlet\Compiler\Llk\Rules\TokenRule(24, 'skipped', null, -1, false),
                25 => new \Hamlet\Compiler\Llk\Rules\ConcatenationRule(25, [17, 18, 23, 24], '#skipped'),
                26 => new \Hamlet\Compiler\Llk\Rules\TokenRule(26, 'kept_', null, -1, false),
                27 => new \Hamlet\Compiler\Llk\Rules\TokenRule(27, 'token', null, -1, true),
                28 => new \Hamlet\Compiler\Llk\Rules\TokenRule(28, 'unification_', null, -1, false),
                29 => new \Hamlet\Compiler\Llk\Rules\TokenRule(29, 'unification', null, -1, true),
                30 => new \Hamlet\Compiler\Llk\Rules\TokenRule(30, '_unification', null, -1, false),
                31 => new \Hamlet\Compiler\Llk\Rules\ConcatenationRule(31, [28, 29, 30], null),
                32 => new \Hamlet\Compiler\Llk\Rules\RepetitionRule(32, 0, 1, 31, null),
                33 => new \Hamlet\Compiler\Llk\Rules\TokenRule(33, '_kept', null, -1, false),
                34 => new \Hamlet\Compiler\Llk\Rules\ConcatenationRule(34, [26, 27, 32, 33], '#kept'),
                35 => new \Hamlet\Compiler\Llk\Rules\TokenRule(35, 'token', null, -1, true),
                36 => new \Hamlet\Compiler\Llk\Rules\TokenRule(36, 'named', null, -1, false),
                37 => new \Hamlet\Compiler\Llk\Rules\ConcatenationRule(37, [35, 36], null),
                'simple' => new \Hamlet\Compiler\Llk\Rules\ChoiceRule('simple', [16, 25, 34, 37], null),
                39 => new \Hamlet\Compiler\Llk\Rules\TokenRule(39, 'zero_or_one', null, -1, true),
                40 => new \Hamlet\Compiler\Llk\Rules\TokenRule(40, 'one_or_more', null, -1, true),
                41 => new \Hamlet\Compiler\Llk\Rules\TokenRule(41, 'zero_or_more', null, -1, true),
                42 => new \Hamlet\Compiler\Llk\Rules\TokenRule(42, 'n_to_m', null, -1, true),
                43 => new \Hamlet\Compiler\Llk\Rules\TokenRule(43, 'n_or_more', null, -1, true),
                44 => new \Hamlet\Compiler\Llk\Rules\TokenRule(44, 'exactly_n', null, -1, true),
                'quantifier' => new \Hamlet\Compiler\Llk\Rules\ChoiceRule('quantifier', [39, 40, 41, 42, 43, 44], null),
            ],
            [
            ]
        );

        $this->getRule('rule')->setDefaultId('#rule');
        $this->getRule('rule')->setPPRepresentation(' choice()');
        $this->getRule('choice')->setPPRepresentation(' concatenation() ( ::or:: concatenation() #choice )*');
        $this->getRule('concatenation')->setPPRepresentation(' repetition() ( repetition() #concatenation )*');
        $this->getRule('repetition')->setPPRepresentation(' simple() ( quantifier() #repetition )? <node>?');
        $this->getRule('simple')->setPPRepresentation(' ::capturing_:: choice() ::_capturing:: | ::skipped:: <token> ( ::unification_:: <unification> ::_unification:: )? ::skipped:: #skipped | ::kept_:: <token> ( ::unification_:: <unification> ::_unification:: )? ::_kept:: #kept | <token> ::named::');
        $this->getRule('quantifier')->setPPRepresentation(' <zero_or_one> | <one_or_more> | <zero_or_more> | <n_to_m> | <n_or_more> | <exactly_n>');
    }
}

CODE;
        $this->assertEquals($code, Llk::save($parser, 'Foobar'));
    }
}
