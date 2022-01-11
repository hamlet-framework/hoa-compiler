<?php

namespace Hamlet\Compiler\Llk\Rules;

use Hamlet\Compiler\Exceptions\RuleException;
use PHPUnit\Framework\TestCase;

class RepetitionTest extends TestCase
{
    public function test_constructor(): void
    {
        $name = 'foo';
        $min = 7;
        $max = 42;
        $children = [];
        $id = 'bar';
        $repetition = new RepetitionRule($name, $min, $max, $children, $id);

        $this->assertEquals($name, $repetition->getName());
        $this->assertEquals($min, $repetition->getMin());
        $this->assertEquals($max, $repetition->getMax());
        $this->assertEquals($children, $repetition->getChildren());
        $this->assertEquals($id, $repetition->getNodeId());
        $this->assertFalse($repetition->isInfinite());
    }

    function test_constructor_min_and_max_are_casted_and_bounded(): void
    {
        $name = 'foo';
        $min = -7;
        $max = 42;
        $children = [];
        $id = 'bar';
        $repetition = new RepetitionRule($name, $min, $max, $children, $id);

        $this->assertEquals(0, $repetition->getMin());
        $this->assertEquals($max, $repetition->getMax());
        $this->assertFalse($repetition->isInfinite());
    }

    public function test_constructor_min_is_greater_than_max(): void
    {
        $name = 'foo';
        $min = 2;
        $max = 1;
        $children = [];
        $id = 'bar';

        $this->expectException(RuleException::class);
        $this->expectExceptionMessage('Cannot repeat with a min (2) greater than max (1).');
        new RepetitionRule($name, $min, $max, $children, $id);
    }

    public function test_constructor_infinite_max(): void
    {
        $name = 'foo';
        $min = 2;
        $max = -1;
        $children = [];
        $id = 'bar';
        $repetition = new RepetitionRule($name, $min, $max, $children, $id);

        $this->assertEquals($min, $repetition->getMin());
        $this->assertEquals($max, $repetition->getMax());
        $this->assertTrue($repetition->isInfinite());
    }
}
