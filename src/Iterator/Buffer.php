<?php

namespace Hoa\Iterator;

use Iterator;
use SplDoublyLinkedList;

/**
 * @template I
 * @template T
 * @implements Iterator<I,T>
 */
final class Buffer implements Iterator
{
    /**
     * Buffer key index.
     * @const int
     */
    private const BUFFER_KEY = 0;

    /**
     * Buffer value index.
     * @const int
     */
    private const BUFFER_VALUE = 1;

    /**
     * Buffer.
     * @var SplDoublyLinkedList<int,array{I,T}>
     */
    protected SplDoublyLinkedList $buffer;

    /**
     * Maximum buffer size.
     */
    protected int $bufferSize = 1;

    /**
     * @param Iterator<I,T> $iterator
     * @param int $bufferSize
     */
    public function __construct(private Iterator $iterator, int $bufferSize)
    {
        $this->bufferSize = max($bufferSize, 1);
        $this->buffer = new SplDoublyLinkedList;
    }

    /**
     * Get inner iterator.
     */
    public function getInnerIterator(): Iterator
    {
        return $this->iterator;
    }

    /**
     * Get buffer.
     */
    protected function getBuffer(): SplDoublyLinkedList
    {
        return $this->buffer;
    }

    /**
     * Get buffer size.
     */
    public function getBufferSize(): int
    {
        return $this->bufferSize;
    }

    /**
     * Return the current element.
     * @return T
     */
    public function current(): mixed
    {
        return $this->getBuffer()->current()[self::BUFFER_VALUE];
    }

    /**
     * Return the key of the current element.
     * @return I
     */
    public function key(): mixed
    {
        return $this->getBuffer()->current()[self::BUFFER_KEY];
    }

    /**
     * Move forward to next element.
     */
    public function next(): void
    {
        $innerIterator = $this->getInnerIterator();
        $buffer = $this->getBuffer();

        $buffer->next();

        // End of the buffer, need a new value.
        if (false === $buffer->valid()) {
            $maximumBufferSize = $this->getBufferSize();
            for ($bufferSize = count($buffer); $bufferSize >= $maximumBufferSize; --$bufferSize) {
                $buffer->shift();
            }
            $innerIterator->next();
            $buffer->push([
                self::BUFFER_KEY => $innerIterator->key(),
                self::BUFFER_VALUE => $innerIterator->current()
            ]);

            // Seek to the end of the buffer.
            $buffer->setIteratorMode($buffer::IT_MODE_LIFO | $buffer::IT_MODE_KEEP);
            $buffer->rewind();
            $buffer->setIteratorMode($buffer::IT_MODE_FIFO | $buffer::IT_MODE_KEEP);
        }
    }

    /**
     * Move backward to previous element.
     */
    public function previous(): void
    {
        $this->getBuffer()->prev();
    }

    /**
     * Rewind the iterator to the first element.
     */
    public function rewind(): void
    {
        $innerIterator = $this->getInnerIterator();
        $buffer = $this->getBuffer();
        $innerIterator->rewind();
        if ($buffer->isEmpty()) {
            $buffer->push([
                self::BUFFER_KEY => $innerIterator->key(),
                self::BUFFER_VALUE => $innerIterator->current()
            ]);
        }
        $buffer->rewind();
    }

    /**
     * Check if current position is valid.
     */
    public function valid(): bool
    {
        return $this->getBuffer()->valid() && $this->getInnerIterator()->valid();
    }
}
