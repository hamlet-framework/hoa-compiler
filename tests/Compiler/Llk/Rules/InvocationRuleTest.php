<?php

namespace Hoa\Compiler\Llk\Rules;

use PHPUnit\Framework\TestCase;

class InvocationTest extends TestCase
{
    public function test_constructor(): void
    {
        $rule = 'foo';
        $data = 'bar';
        $invocation = new class($rule, $data) extends InvocationRule {
        };

        $this->assertEquals($rule, $invocation->getRule());
        $this->assertEquals($data, $invocation->getData());
        $this->assertNull($invocation->getTodo());
        $this->assertEquals(-1, $invocation->getDepth());
        $this->assertFalse($invocation->isTransitional());
    }

    public function test_constructor_with_todo(): void
    {
        $rule = 'foo';
        $data = 'bar';
        $todo = ['baz', 'qux'];
        $invocation = new class($rule, $data, $todo) extends InvocationRule {
        };

        $this->assertEquals($rule, $invocation->getRule());
        $this->assertEquals($data, $invocation->getData());
        $this->assertEquals($todo, $invocation->getTodo());
        $this->assertEquals(-1, $invocation->getDepth());
        $this->assertFalse($invocation->isTransitional());
    }

    public function test_constructor_with_todo_and_depth(): void
    {
        $rule = 'foo';
        $data = 'bar';
        $todo = ['baz', 'qux'];
        $depth = 42;
        $invocation = new class($rule, $data, $todo, $depth) extends InvocationRule {
        };

        $this->assertEquals($rule, $invocation->getRule());
        $this->assertEquals($data, $invocation->getData());
        $this->assertEquals($todo, $invocation->getTodo());
        $this->assertEquals($depth, $invocation->getDepth());
        $this->assertFalse($invocation->isTransitional());
    }

    public function test_set_depth(): void
    {
        $rule = 42;
        $data = 'bar';
        $depth = 42;
        $invocation = new class($rule, $data) extends InvocationRule {
        };

        $this->assertEquals(-1, $invocation->setDepth($depth));
        $this->assertEquals($depth, $invocation->getDepth());
    }

    public function test_is_transitional(): void
    {
        $rule = 7;
        $data = 'bar';
        $invocation = new class($rule, $data) extends InvocationRule {
        };

        $this->assertTrue($invocation->isTransitional());
    }

    public function test_is_not_transitional(): void
    {
        $rule = 'a';
        $data = 'bar';
        $invocation = new class($rule, $data) extends InvocationRule {
        };

        $this->assertFalse($invocation->isTransitional());
    }
}