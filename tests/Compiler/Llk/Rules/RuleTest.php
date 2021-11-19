<?php

namespace Hoa\Compiler\Llk\Rules;

use PHPUnit\Framework\TestCase;

class RuleTest extends TestCase
{
    public function test_constructor(): void
    {
        $name = 'foo';
        $children = ['bar'];
        $rule = new class($name, $children) extends Rule {};

        $this->assertEquals($name, $rule->getName());
        $this->assertEquals($children, $rule->getChildren());
        $this->assertNull($rule->getNodeId());
        $this->assertTrue($rule->isTransitional());
    }

    public function test_constructor_with_node_id(): void
    {
        $name = 'foo';
        $children = ['bar'];
        $nodeId = 'baz';
        $rule = new class($name, $children, $nodeId) extends Rule {};

        $this->assertEquals($name, $rule->getName());
        $this->assertEquals($children, $rule->getChildren());
        $this->assertEquals($nodeId, $rule->getNodeId());
        $this->assertTrue($rule->isTransitional());
    }

    public function test_set_and_get_name(): void
    {
        $name = 'foo';
        $children = ['bar'];
        $rule = new class($name, $children) extends Rule {};

        $name2 = 'baz';
        $this->assertEquals($name, $rule->setName($name2));
        $this->assertEquals($name2, $rule->getName());
    }

    public function test_set_and_get_children(): void
    {
        $name = 'foo';
        $children = ['bar'];
        $rule = new class($name, $children) extends Rule
        {
            public function setChildren(array|string|int $children): array|string|int
            {
                return parent::setChildren($children);
            }
        };

        $children2 = ['baz'];
        $this->assertEquals($children, $rule->setChildren($children2));
        $this->assertEquals($children2, $rule->getChildren());
    }

    public function test_set_and_get_node_id(): void
    {
        $name = 'foo';
        $children = ['bar'];
        $nodeId = 'id';
        $rule = new class($name, $children, $nodeId) extends Rule {};

        $nodeId2 = 'baz';
        $this->assertEquals($nodeId, $rule->setNodeId($nodeId2));
        $this->assertEquals($nodeId2, $rule->getNodeId());
    }

    public function test_get_node_id_with_options(): void
    {
        $name = 'foo';
        $children = ['bar'];
        $rule = new class($name, $children) extends Rule {};

        $rule->setNodeId('baz:qux');
        $this->assertEquals('baz', $rule->getNodeId());
    }

    public function test_get_node_options_empty(): void
    {
        $name = 'foo';
        $children = ['bar'];
        $rule = new class($name, $children) extends Rule {};
        $rule->setNodeId('baz');

        $this->assertEmpty($rule->getNodeOptions());
    }

    public function test_get_node_options(): void
    {
        $name = 'foo';
        $children = ['bar'];
        $rule = new class($name, $children) extends Rule {};
        $rule->setNodeId('baz:qux');

        $this->assertEquals(['q', 'u', 'x'], $rule->getNodeOptions());
    }

    public function test_set_and_get_default_id(): void
    {
        $name = 'foo';
        $children = ['bar'];
        $rule = new class($name, $children) extends Rule {};

        $defaultId = 'baz';
        $this->assertNull($rule->setDefaultId($defaultId));
        $this->assertEquals($defaultId, $rule->getDefaultId());
        $this->assertEmpty($rule->getDefaultOptions());
    }

    public function test_set_and_get_default_id_with_options(): void
    {
        $name = 'foo';
        $children = ['bar'];
        $rule = new class($name, $children) extends Rule {};

        $this->assertNull($rule->setDefaultId('baz:qux'));
        $this->assertEquals('baz', $rule->getDefaultId());
        $this->assertEquals(['q', 'u', 'x'], $rule->getDefaultOptions());
    }

    public function test_set_and_get_pp_representation(): void
    {
        $name = 'foo';
        $children = ['bar'];
        $pp  = '<a> ::b:: c()?';
        $rule = new class($name, $children) extends Rule {};

        $this->assertTrue($rule->isTransitional());
        $this->assertNull($rule->setPPRepresentation($pp));
        $this->assertFalse($rule->isTransitional());
        $this->assertEquals($pp, $rule->getPPRepresentation());
    }
}
