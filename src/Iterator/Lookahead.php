<?php

namespace Hoa\Iterator;

use Iterator;

/**
 * @template I
 * @template T
 * @implements Iterator<I,T>
 */
final class Lookahead implements Iterator
{
    /**
     * Current key.
     * @var I|null
     */
    private mixed $key = null;

    /**
     * Current value.
     * @var T|null
     */
    private mixed $current = null;

    /**
     * Whether the current element is valid or not.
     */
    private bool $valid = false;

    /**
     * @param Iterator<I,T> $iterator
     */
    public function __construct(private Iterator $iterator)
    {
    }

    /**
     * Get inner iterator.
     * @return Iterator<I,T>
     */
    public function getInnerIterator(): Iterator
    {
        return $this->iterator;
    }

    /**
     * Return the current element.
     * @return T|null
     */
    public function current(): mixed
    {
        return $this->current;
    }

    /**
     * Return the key of the current element.
     * @return I|null
     */
    public function key(): mixed
    {
        return $this->key;
    }

    /**
     * Move forward to next element.
     */
    public function next(): void
    {
        $innerIterator = $this->getInnerIterator();
        $this->valid = $innerIterator->valid();

        if (false === $this->valid) {
            return;
        }

        $this->key = $innerIterator->key();
        $this->current = $innerIterator->current();

        $innerIterator->next();
    }

    /**
     * Rewind the iterator to the first element.
     */
    public function rewind(): void
    {
        $this->getInnerIterator()->rewind();
        $this->next();
    }

    /**
     * Check if current position is valid.
     */
    public function valid(): bool
    {
        return $this->valid;
    }

    /**
     * Check whether there is a next element.
     */
    public function hasNext(): bool
    {
        return $this->getInnerIterator()->valid();
    }

    /**
     * Get next value.
     * @return T|null
     */
    public function getNext(): mixed
    {
        return $this->getInnerIterator()->current();
    }

    /**
     * Get next key.
     * @return I|null
     */
    public function getNextKey(): mixed
    {
        return $this->getInnerIterator()->key();
    }
}
