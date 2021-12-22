<?php

namespace Hoa\Compiler\Llk;

use Generator;
use Hoa\Compiler\Exceptions\LexerException;
use Hoa\Compiler\Exceptions\UnrecognizedTokenException;
use Iterator;
use PHPUnit\Framework\TestCase;

class LexerTest extends TestCase
{
    /**
     * @param Iterator<Token> $iterator
     * @return array
     */
    static function token_iterator_to_array(Iterator $iterator): array
    {
        return array_map(fn (Token $token) => $token->toArray(), iterator_to_array($iterator));
    }

    public function test_lex_me_result_is_a_generator(): void
    {
        $lexer = new Lexer;
        $datum = 'abs';
        $tokens = [
            'default' => [
                'abc' => 'abc'
            ]
        ];

        $result = $lexer->lexMe($datum, $tokens);

        $this->assertInstanceOf(Generator::class, $result);
    }

    public function test_last_token_is_eof(): void
    {
        $lexer = new Lexer;
        $datum = 'ghidefabc';
        $tokens = [
            'default' => [
                'abc'  => 'abc',
                'def'  => 'def',
                'tail' => '\w{3}'
            ]
        ];

        $result = $lexer->lexMe($datum, $tokens);

        $current = $result->current();
        $this->assertEquals([
            'token'     => 'tail',
            'value'     => 'ghi',
            'length'    => 3,
            'namespace' => 'default',
            'keep'      => true,
            'offset'    => 0
        ], $current->toArray());

        $result->next();
        $current = $result->current();
        $this->assertEquals([
            'token'     => 'def',
            'value'     => 'def',
            'length'    => 3,
            'namespace' => 'default',
            'keep'      => true,
            'offset'    => 3
        ], $current->toArray());

        $result->next();
        $current = $result->current();
        $this->assertEquals([
            'token'     => 'abc',
            'value'     => 'abc',
            'length'    => 3,
            'namespace' => 'default',
            'keep'      => true,
            'offset'    => 6
        ], $current->toArray());

        $result->next();
        $current = $result->current();
        $this->assertEquals([
            'token'     => 'EOF',
            'value'     => 'EOF',
            'length'    => 0,
            'namespace' => 'default',
            'keep'      => true,
            'offset'    => 9
        ], $current->toArray());

        $result->next();
        $current = $result->current();
        $this->assertNull($current);
    }

    public function test_unrecognized_token(): void
    {
        $lexer = new Lexer;
        $datum = 'abczdef';
        $tokens = [
            'default' => [
                'abc'  => 'abc',
                'def'  => 'def',
            ]
        ];
        $result = $lexer->lexMe($datum, $tokens);

        $this->assertEquals([
            'token'     => 'abc',
            'value'     => 'abc',
            'length'    => 3,
            'namespace' => 'default',
            'keep'      => true,
            'offset'    => 0
        ], $result->current()->toArray());

        $this->expectException(UnrecognizedTokenException::class);
        $this->expectExceptionMessage(
            'Unrecognized token "z" at line 1 and column 4:' . "\n" .
            'abczdef' . "\n" .
            '   ↑'
        );
        $result->next();
    }

    public function test_namespaces(): void
    {
        $lexer = new Lexer;
        $datum = 'abcdefghiabc';
        $tokens = [
            'default' => ['abc:one'     => 'abc'],
            'one'     => ['def:two'     => 'def'],
            'two'     => ['ghi:default' => 'ghi']
        ];
        $result = $lexer->lexMe($datum, $tokens);

        $this->assertEquals([
            [
                'token'     => 'abc',
                'value'     => 'abc',
                'length'    => 3,
                'namespace' => 'default',
                'keep'      => true,
                'offset'    => 0
            ],
            [
                'token'     => 'def',
                'value'     => 'def',
                'length'    => 3,
                'namespace' => 'one',
                'keep'      => true,
                'offset'    => 3
            ],
            [
                'token'     => 'ghi',
                'value'     => 'ghi',
                'length'    => 3,
                'namespace' => 'two',
                'keep'      => true,
                'offset'    => 6
            ],
            [
                'token'     => 'abc',
                'value'     => 'abc',
                'length'    => 3,
                'namespace' => 'default',
                'keep'      => true,
                'offset'    => 9
            ],
            [
                'token'     => 'EOF',
                'value'     => 'EOF',
                'length'    => 0,
                'namespace' => 'default',
                'keep'      => true,
                'offset'    => 12
            ]
        ], self::token_iterator_to_array($result));
    }

    public function test_namespace_with_shift(): void
    {
        $lexer = new Lexer;
        $datum = 'abcdefghiabc';
        $tokens = [
            'default' => ['abc:one'           => 'abc'],
            'one'     => ['def:two'           => 'def'],
            'two'     => ['ghi:__shift__ * 2' => 'ghi'],
        ];
        $result = $lexer->lexMe($datum, $tokens);

        $this->assertEquals([
            [
                'token'     => 'abc',
                'value'     => 'abc',
                'length'    => 3,
                'namespace' => 'default',
                'keep'      => true,
                'offset'    => 0
            ],
            [
                'token'     => 'def',
                'value'     => 'def',
                'length'    => 3,
                'namespace' => 'one',
                'keep'      => true,
                'offset'    => 3
            ],
            [
                'token'     => 'ghi',
                'value'     => 'ghi',
                'length'    => 3,
                'namespace' => 'two',
                'keep'      => true,
                'offset'    => 6
            ],
            [
                'token'     => 'abc',
                'value'     => 'abc',
                'length'    => 3,
                'namespace' => 'default',
                'keep'      => true,
                'offset'    => 9
            ],
            [
                'token'     => 'EOF',
                'value'     => 'EOF',
                'length'    => 0,
                'namespace' => 'default',
                'keep'      => true,
                'offset'    => 12
            ]
        ], self::token_iterator_to_array($result));
    }

    public function test_namespace_shift_too_much(): void
    {
        $lexer = new Lexer;
        $datum = 'abcdefghiabc';
        $tokens = [
            'default' => ['abc:__shift__' => 'abc'],
        ];
        $result = $lexer->lexMe($datum, $tokens);

        $this->expectException(LexerException::class);
        $this->expectExceptionMessage(
            'Cannot shift namespace 1-times, from token abc ' .
            'in namespace default, because the stack contains ' .
            'only 0 namespaces.'
        );
        $result->next();
    }

    public function test_namespace_does_not_exist(): void
    {
        $lexer = new Lexer;
        $datum = 'abcdef';
        $tokens = [
            'default' => [
                'abc:foo' => 'abc',
                'def'     => 'def',
            ]
        ];
        $result = $lexer->lexMe($datum, $tokens);

        $this->expectException(LexerException::class);
        $this->expectExceptionMessage(
            'Namespace foo does not exist, called by token abc ' .
            'in namespace default.'
        );
        $result->next();
    }

    public function test_skip(): void
    {
        $lexer = new Lexer;
        $datum = 'abc def   ghi  abc';
        $tokens = [
            'default' => [
                'skip' => '\s+',
                'abc'  => 'abc',
                'def'  => 'def',
                'ghi'  => 'ghi',
            ]
        ];
        $result = $lexer->lexMe($datum, $tokens);

        $this->assertEquals([
            [
                'token'     => 'abc',
                'value'     => 'abc',
                'length'    => 3,
                'namespace' => 'default',
                'keep'      => true,
                'offset'    => 0
            ],
            [
                'token'     => 'def',
                'value'     => 'def',
                'length'    => 3,
                'namespace' => 'default',
                'keep'      => true,
                'offset'    => 4
            ],
            [
                'token'     => 'ghi',
                'value'     => 'ghi',
                'length'    => 3,
                'namespace' => 'default',
                'keep'      => true,
                'offset'    => 10
            ],
            [
                'token'     => 'abc',
                'value'     => 'abc',
                'length'    => 3,
                'namespace' => 'default',
                'keep'      => true,
                'offset'    => 15
            ],
            [
                'token'     => 'EOF',
                'value'     => 'EOF',
                'length'    => 0,
                'namespace' => 'default',
                'keep'      => true,
                'offset'    => 18
            ]
        ], self::token_iterator_to_array($result));
    }

    public function test_match_empty_lexeme(): void
    {
        $lexer = new Lexer;
        $datum = 'abcdef';
        $tokens = [
            'default' => [
                'abc' => '\d?',
                'def' => 'def',
            ]
        ];
        $result = $lexer->lexMe($datum, $tokens);

        $this->expectException(LexerException::class);
        $this->expectExceptionMessage(
            'A lexeme must not match an empty value, which is ' .
            'the case of "abc" (\d?).'
        );
        $result->next();
    }

    public function test_unicode_enabled_by_default(): void
    {
        $lexer = new Lexer;
        $datum = '…ß';
        $tokens = [
            'default' => [
                'foo' => '…',
                'bar' => '\w',
            ]
        ];
        $result = $lexer->lexMe($datum, $tokens);

        $this->assertEquals([
            [
                'token'     => 'foo',
                'value'     => '…',
                'length'    => 1,
                'namespace' => 'default',
                'keep'      => true,
                'offset'    => 0
            ],
            [
                'token'     => 'bar',
                'value'     => 'ß',
                'length'    => 1,
                'namespace' => 'default',
                'keep'      => true,
                'offset'    => 3
            ],
            [
                'token'     => 'EOF',
                'value'     => 'EOF',
                'length'    => 0,
                'namespace' => 'default',
                'keep'      => true,
                'offset'    => 5
            ]
        ], self::token_iterator_to_array($result));
    }

    public function test_unicode_disabled(): void
    {
        $lexer = new Lexer(['lexer.unicode' => false]);
        $datum = '…ß';
        $tokens = [
            'default' => [
                'foo' => '…',
                'bar' => '\w',
            ]
        ];
        $result = $lexer->lexMe($datum, $tokens);

        $this->expectException(UnrecognizedTokenException::class);
        $this->expectExceptionMessage(
            'Unrecognized token "ß" at line 1 and column 4:' . "\n" .
            '…ß' . "\n" .
            ' ↑'
        );
        $result->next();
    }
}

