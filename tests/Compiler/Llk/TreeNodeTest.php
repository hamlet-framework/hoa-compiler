<?php

namespace Hoa\Compiler\Llk;

use PHPUnit\Framework\TestCase;

class TreeNodeTest extends TestCase
{
    public function test_constructor(): void
    {
        $id = 'foo';
        $node = new TreeNode($id);

        $this->assertEquals($id, $node->getId());
        $this->assertNull($node->getValue());
        $this->assertEquals(0, $node->getChildrenNumber());
        $this->assertEmpty($node->getChildren());
        $this->assertNull($node->getParent());
    }

    public function test_constructor_with_a_value(): void
    {
        $id = 'foo';
        $value = ['bar'];
        $node = new TreeNode($id, $value);

        $this->assertEquals($id, $node->getId());
        $this->assertEquals($value, $node->getValue());
        $this->assertEquals(0, $node->getChildrenNumber());
        $this->assertEmpty($node->getChildren());
        $this->assertNull($node->getParent());
    }

    public function test_constructor_with_a_value_and_children(): void
    {
        $id = 'foo';
        $value = ['bar'];
        $children = [
            new TreeNode('baz'),
            new TreeNode('qux'),
        ];
        $node = new TreeNode($id, $value, $children);

        $this->assertEquals($id, $node->getId());
        $this->assertEquals($value, $node->getValue());
        $this->assertEquals(2, $node->getChildrenNumber());
        $this->assertEquals($children, $node->getChildren());
        $this->assertNull($node->getParent());
    }

    public function test_constructor_with_a_value_and_children_and_a_parent(): void
    {
        $id = 'foo';
        $value = ['bar'];
        $children = [
            new TreeNode('baz'),
            new TreeNode('qux'),
        ];
        $parent = new TreeNode('root');
        $node = new TreeNode($id, $value, $children, $parent);

        $this->assertEquals($id, $node->getId());
        $this->assertEquals($value, $node->getValue());
        $this->assertEquals(2, $node->getChildrenNumber());
        $this->assertEquals($children, $node->getChildren());
        $this->assertEquals($parent, $node->getParent());
    }

    public function test_set_and_get_id(): void
    {
        $node = new TreeNode('foo');

        $result = $node->setId('bar');

        $this->assertEquals('foo', $result);
        $this->assertEquals('bar', $node->getId());
    }

    public function test_get_value(): void
    {
        $node = new TreeNode('foo', ['bar']);

        $this->assertEquals(['bar'], $node->getValue());
    }

    public function test_get_value_token(): void
    {
        $node = new TreeNode('foo', ['token' => 'bar']);

        $result = $node->getValueToken();

        $this->assertEquals('bar', $result);
    }

    public function test_get_undefined_value_token(): void
    {
        $node = new TreeNode('foo', ['bar']);

        $result = $node->getValueToken();

        $this->assertNull($result);
    }

    public function test_get_value_value(): void
    {
        $node = new TreeNode('foo', ['value' => 'bar']);

        $result = $node->getValueValue();

        $this->assertEquals('bar', $result);
    }

    public function test_get_undefined_value_value(): void
    {
        $node = new TreeNode('foo', ['bar']);

        $result = $node->getValueValue();

        $this->assertNull($result);
    }

    public function test_is_token(): void
    {
        $node = new TreeNode('foo', ['bar']);

        $this->assertTrue($node->isToken());
    }

    public function test_is_not_token(): void
    {
        $node = new TreeNode('foo');

        $this->assertFalse($node->isToken());
    }

    public function test_prepend_child(): void
    {
        $childA = new TreeNode('baz');
        $childB = new TreeNode('qux');
        $node = new TreeNode('foo', ['bar'], [$childA]);

        $result = $node->prependChild($childB);

        $this->assertSame($node, $result);
        $this->assertEquals(2, $node->getChildrenNumber());
        $this->assertEquals([$childB, $childA], $node->getChildren());
    }

    public function test_append_child(): void
    {
        $childA = new TreeNode('baz');
        $childB = new TreeNode('qux');
        $node = new TreeNode('foo', ['bar'], [$childA]);

        $result = $node->appendChild($childB);

        $this->assertSame($node, $result);
        $this->assertEquals(2, $node->getChildrenNumber());
        $this->assertEquals([$childA, $childB], $node->getChildren());
    }

    public function test_set_children(): void
    {
        $childA = new TreeNode('baz');
        $childB = new TreeNode('qux');
        $childC = new TreeNode('hello');
        $node = new TreeNode('foo', ['bar'], [$childA]);

        $result = $node->setChildren([$childB, $childC]);

        $this->assertEquals([$childA], $result);
        $this->assertEquals(2, $node->getChildrenNumber());
        $this->assertEquals([$childB, $childC], $node->getChildren());
    }

    public function test_get_child(): void
    {
        $childA = new TreeNode('baz');
        $childB = new TreeNode('qux');
        $node = new TreeNode('foo', ['bar'], [$childA, $childB]);

        $result = $node->getChild(0);
        $this->assertSame($childA, $result);

        $result = $node->getChild(1);
        $this->assertSame($childB, $result);
    }

    public function test_get_undefined_child(): void
    {
        $node = new TreeNode('foo', ['bar']);

        $result = $node->getChild(0);

        $this->assertNull($result);
    }

    public function test_get_children(): void
    {
        $childA = new TreeNode('baz');
        $childB = new TreeNode('qux');
        $node = new TreeNode('foo', ['bar'], [$childA, $childB]);

        $result = $node->getChildren();

        $this->assertEquals([$childA, $childB], $result);
    }

    public function test_get_children_number(): void
    {
        $childA = new TreeNode('baz');
        $childB = new TreeNode('qux');
        $node = new TreeNode('foo', ['bar']);

        $result = $node->getChildrenNumber();
        $this->assertEquals(0, $result);

        $node->setChildren([$childA, $childB]);
        $result = $node->getChildrenNumber();
        $this->assertEquals(2, $result);
    }

    public function test_child_exists(): void
    {
        $node = new TreeNode('foo', ['bar'], [new TreeNode('baz')]);

        $this->assertTrue($node->childExists(0));
    }

    public function test_child_does_not_exist(): void
    {
        $node = new TreeNode('foo', ['bar']);

        $this->assertFalse($node->childExists(0));
    }

    public function test_set_parent(): void
    {
        $parent = new TreeNode('baz');
        $node = new TreeNode('foo', ['bar'], [], $parent);

        $this->assertSame($parent, $node->getParent());
    }

    public function test_get_parent(): void
    {
        $parent = new TreeNode('qux');
        $node = new TreeNode('foo', ['bar'], [], new TreeNode('baz'));

        $node->setParent($parent);

        $this->assertSame($parent, $node->getParent());
    }

    public function test_get_data_by_value(): void
    {
        $node = new TreeNode('foo');

        $result = $node->getData();
        $this->assertIsArray($result);
        $this->assertEmpty($result);

        $result[] = 'bar';
        $result[] = 'baz';

        $result = $node->getData();
        $this->assertEmpty($result);
    }

    public function test_get_data_by_reference(): void
    {
        $node = new TreeNode('foo');

        $result = &$node->getData();
        $this->assertIsArray($result);
        $this->assertEmpty($result);

        $result[] = 'bar';
        $result[] = 'baz';

        $result = $node->getData();
        $this->assertEquals(['bar', 'baz'], $result);
    }
}
