<?php

namespace Hoa\Iterator;

use JakubOnderka\PhpParallelLint\ArrayIterator;
use PHPUnit\Framework\TestCase;

class LookaheadTest extends TestCase
{
    public function test_traverse(): void
    {
        $iterators = new ArrayIterator(['a', 'b', 'c']);
        $lookahead = new Lookahead($iterators);

        $this->assertEquals(['a', 'b', 'c'], iterator_to_array($lookahead));
    }

    public function test_empty(): void
    {
        $lookahead = new Lookahead(new ArrayIterator());
        $lookahead->rewind();

        $this->assertFalse($lookahead->valid());
    }

    public function test_check_ahead(): void
    {
        $iterators = new ArrayIterator(['a', 'b', 'c']);
        $lookahead = new Lookahead($iterators);

        $lookahead->rewind();
        $this->assertEquals(0, $lookahead->key());
        $this->assertEquals('a', $lookahead->current());
        $this->assertTrue($lookahead->hasNext());
        $this->assertEquals(1, $lookahead->getNextKey());
        $this->assertEquals('b', $lookahead->getNext());

        $lookahead->next();
        $this->assertEquals(1, $lookahead->key());
        $this->assertEquals('b', $lookahead->current());
        $this->assertTrue($lookahead->hasNext());
        $this->assertEquals(2, $lookahead->getNextKey());
        $this->assertEquals('c', $lookahead->getNext());

        $lookahead->next();
        $this->assertEquals(2, $lookahead->key());
        $this->assertEquals('c', $lookahead->current());
        $this->assertFalse($lookahead->hasNext());
        $this->assertNull($lookahead->getNextKey());
        $this->assertNull($lookahead->getNext());
    }

    public function test_double_rewind(): void
    {
        $iterators = new ArrayIterator(['a', 'b', 'c']);
        $lookahead = new Lookahead($iterators);

        $lookahead->rewind();
        $this->assertEquals(0, $lookahead->key());
        $this->assertEquals('a', $lookahead->current());
        $this->assertTrue($lookahead->hasNext());
        $this->assertEquals(1, $lookahead->getNextKey());
        $this->assertEquals('b', $lookahead->getNext());

        $lookahead->rewind();
        $this->assertEquals(0, $lookahead->key());
        $this->assertEquals('a', $lookahead->current());
        $this->assertTrue($lookahead->hasNext());
        $this->assertEquals(1, $lookahead->getNextKey());
        $this->assertEquals('b', $lookahead->getNext());
    }
}
