<?php

namespace Hamlet\Compiler\Llk;

use Hamlet\Compiler\Visitor\Dump;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
    public function test_json_1(): void
    {
        $path = __DIR__ . '/../../../src/Grammars/Json.pp';
        $compiler = Llk::load($path);
        $ast = $compiler->parse('{"foo": true, "bar": [null, 42]}');

        $dump = $code = <<<'DUMP'
>  #object
>  >  #pair
>  >  >  token(string:string, foo)
>  >  >  token(true, true)
>  >  #pair
>  >  >  token(string:string, bar)
>  >  >  #array
>  >  >  >  token(null, null)
>  >  >  >  token(number, 42)

DUMP;
        $this->assertEquals($dump, (new Dump)->visit($ast));
    }

    public function test_json_2(): void
    {
        $path = __DIR__ . '/../../../src/Grammars/Json.pp';
        $compiler = Llk::load($path);
        $ast = $compiler->parse('[1, [1, [2, 3], 5], 8]');

        $dump = <<<'DUMP'
>  #array
>  >  token(number, 1)
>  >  #array
>  >  >  token(number, 1)
>  >  >  #array
>  >  >  >  token(number, 2)
>  >  >  >  token(number, 3)
>  >  >  token(number, 5)
>  >  token(number, 8)

DUMP;
        $this->assertEquals($dump, (new Dump)->visit($ast));
    }

    public function test_psalm_1(): void
    {
        $path = __DIR__ . '/../../Grammars/Psalm.pp';
        $compiler = Llk::load($path);
        $ast = $compiler->parse('callable(\\Iterator<I>):(array{float,int}|false)');

        $dump = <<<'DUMP'
>  #basic_type
>  >  #callable
>  >  >  token(callable, callable)
>  >  >  #basic_type
>  >  >  >  #generic
>  >  >  >  >  #class_name
>  >  >  >  >  >  #backslash
>  >  >  >  >  >  token(id, Iterator)
>  >  >  >  >  #basic_type
>  >  >  >  >  >  #class_name
>  >  >  >  >  >  >  token(id, I)
>  >  >  #basic_type
>  >  >  >  #union
>  >  >  >  >  #basic_type
>  >  >  >  >  >  #object_like_array
>  >  >  >  >  >  >  token(array, array)
>  >  >  >  >  >  >  #property
>  >  >  >  >  >  >  >  #basic_type
>  >  >  >  >  >  >  >  >  token(built_in, float)
>  >  >  >  >  >  >  #property
>  >  >  >  >  >  >  >  #basic_type
>  >  >  >  >  >  >  >  >  token(built_in, int)
>  >  >  >  >  #basic_type
>  >  >  >  >  >  #literal
>  >  >  >  >  >  >  token(false, false)

DUMP;
        $this->assertEquals($dump, (new Dump)->visit($ast));
    }
}