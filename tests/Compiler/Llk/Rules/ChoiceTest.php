<?php

namespace Hoa\Compiler\Llk\Rules;

use PHPUnit\Framework\TestCase;

class ChoiceTest extends TestCase
{
    public function test_case_is_a_rule(): void
    {
        $choice = new Choice('foo', ['bar']);

        $this->assertEquals('foo', $choice->getName());
    }
}
