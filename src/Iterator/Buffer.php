<?php

namespace Hoa\Iterator;

use Iterator;
use SplDoublyLinkedList;

/**
 * @template I
 * @template T
 * @implements Iterator<I,T>
 * @todo rewtite this code as this is rather shit
 */
final class Buffer implements Iterator
{
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
     * @psalm-suppress MixedPropertyTypeCoercion
     */
    public function __construct(private Iterator $iterator, int $bufferSize)
    {
        $this->bufferSize = max($bufferSize, 1);
        $this->buffer = new SplDoublyLinkedList;
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
        return $this->buffer->current()[1];
    }

    /**
     * Return the key of the current element.
     * @return I
     */
    public function key(): mixed
    {
        return $this->buffer->current()[0];
    }

    /**
     * Move forward to next element.
     */
    public function next(): void
    {
        $buffer = $this->buffer;

        $buffer->next();

        // End of the buffer, need a new value.
        if (!$buffer->valid()) {
            $maximumBufferSize = $this->getBufferSize();
            for ($bufferSize = count($buffer); $bufferSize >= $maximumBufferSize; --$bufferSize) {
                $buffer->shift();
            }
            $this->iterator->next();
            $buffer->push([$this->iterator->key(), $this->iterator->current()]);

            // Seek to the end of the buffer.
            $buffer->setIteratorMode(SplDoublyLinkedList::IT_MODE_LIFO | SplDoublyLinkedList::IT_MODE_KEEP);
            $buffer->rewind();
            $buffer->setIteratorMode(SplDoublyLinkedList::IT_MODE_FIFO | SplDoublyLinkedList::IT_MODE_KEEP);
        }
    }

    /**
     * Move backward to previous element.
     */
    public function previous(): void
    {
        $this->buffer->prev();
    }

    /**
     * Rewind the iterator to the first element.
     */
    public function rewind(): void
    {
        $buffer = $this->buffer;
        $this->iterator->rewind();
        if ($buffer->isEmpty()) {
            $buffer->push([$this->iterator->key(), $this->iterator->current()]);
        }
        $buffer->rewind();
    }

    /**
     * Check if current position is valid.
     */
    public function valid(): bool
    {
        return $this->buffer->valid() && $this->iterator->valid();
    }
}
