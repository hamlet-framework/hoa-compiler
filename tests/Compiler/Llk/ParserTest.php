<?php

namespace Hoa\Compiler\Llk;

use Hoa\Compiler\Visitor\Dump;
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
}