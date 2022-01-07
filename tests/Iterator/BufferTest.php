<?php

namespace Hoa\Iterator;

use ArrayIterator;
use PHPUnit\Framework\TestCase;

class BufferTest extends TestCase
{
    public function test_negative_buffer_size(): void
    {
        $innerIterator = new ArrayIterator(['a', 'b', 'c', 'd', 'e']);
        $buffer = new Buffer($innerIterator, -21);

        $this->assertEquals(1, $buffer->getBufferSize());
    }

    public function test_zero_buffer_size(): void
    {
        $innerIterator = new ArrayIterator(['a', 'b', 'c', 'd', 'e']);
        $buffer = new Buffer($innerIterator, 0);

        $this->assertEquals(1, $buffer->getBufferSize());
    }

    public function test_fast_forward(): void
    {
        $array = ['a', 'b', 'c', 'd', 'e'];
        $innerIterator = new ArrayIterator($array);
        $buffer = new Buffer($innerIterator, 3);

        $this->assertEquals($array, iterator_to_array($buffer));
    }

    public function test_forward_forward_forward(): void
    {
        $innerIterator = new ArrayIterator(['a', 'b', 'c']);
        $buffer = new Buffer($innerIterator, 2);

        $buffer->rewind();
        $this->assertTrue($buffer->valid());
        $this->assertEquals(0, $buffer->key());
        $this->assertEquals('a', $buffer->current());

        $buffer->next();
        $this->assertTrue($buffer->valid());
        $this->assertEquals(1, $buffer->key());
        $this->assertEquals('b', $buffer->current());

        $buffer->next();
        $this->assertTrue($buffer->valid());
        $this->assertEquals(2, $buffer->key());
        $this->assertEquals('c', $buffer->current());

        $buffer->next();
        $this->assertFalse($buffer->valid());
        $this->assertNull($buffer->key());
        $this->assertNull($buffer->current());
    }

    public function test_backward_out_of_buffer(): void
    {
        $innerIterator = new ArrayIterator(['a', 'b', 'c']);
        $buffer = new Buffer($innerIterator, 1);

        $buffer->rewind();
        $this->assertTrue($buffer->valid());
        $this->assertEquals(0, $buffer->key());
        $this->assertEquals('a', $buffer->current());

        $buffer->next();
        $this->assertTrue($buffer->valid());
        $this->assertEquals(1, $buffer->key());
        $this->assertEquals('b', $buffer->current());

        $buffer->previous();
        $this->assertFalse($buffer->valid());
    }

    public function test_rewind_rewind(): void
    {
        $innerIterator = new ArrayIterator(['a', 'b']);
        $buffer = new Buffer($innerIterator, 3);

        $buffer->rewind();
        $this->assertTrue($buffer->valid());
        $this->assertEquals(0, $buffer->key());
        $this->assertEquals('a', $buffer->current());

        $buffer->next();
        $buffer->rewind();
        $this->assertTrue($buffer->valid());
        $this->assertEquals(0, $buffer->key());
        $this->assertEquals('a', $buffer->current());
    }

    public function test_forward_forward_backward_backward_forward_forward_forward_step_by_step(): void
    {
        $innerIterator = new ArrayIterator(['a', 'b', 'c']);
        $buffer = new Buffer($innerIterator, 3);

        $buffer->rewind();
        $this->assertTrue($buffer->valid());
        $this->assertEquals(0, $buffer->key());
        $this->assertEquals('a', $buffer->current());

        $buffer->next();
        $this->assertTrue($buffer->valid());
        $this->assertEquals(1, $buffer->key());
        $this->assertEquals('b', $buffer->current());

        $buffer->next();
        $this->assertTrue($buffer->valid());
        $this->assertEquals(2, $buffer->key());
        $this->assertEquals('c', $buffer->current());

        $buffer->previous();
        $this->assertTrue($buffer->valid());
        $this->assertEquals(1, $buffer->key());
        $this->assertEquals('b', $buffer->current());

        $buffer->previous();
        $this->assertTrue($buffer->valid());
        $this->assertEquals(0, $buffer->key());
        $this->assertEquals('a', $buffer->current());

        $buffer->next();
        $this->assertTrue($buffer->valid());
        $this->assertEquals(1, $buffer->key());
        $this->assertEquals('b', $buffer->current());

        $buffer->next();
        $this->assertTrue($buffer->valid());
        $this->assertEquals(2, $buffer->key());
        $this->assertEquals('c', $buffer->current());

        $buffer->next();
        $this->assertFalse($buffer->valid());
        $this->assertNull($buffer->key());
        $this->assertNull($buffer->current());
    }
}
