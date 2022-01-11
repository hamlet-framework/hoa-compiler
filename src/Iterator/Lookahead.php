<?php

namespace Hamlet\Iterator;

use Iterator;
use RuntimeException;

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
     * Return the current element.
     * @return T
     */
    public function current(): mixed
    {
        if ($this->current === null) {
            throw new RuntimeException('Invalid state');
        }
        return $this->current;
    }

    /**
     * Return the key of the current element.
     * @return I
     */
    public function key(): mixed
    {
        if ($this->key === null) {
            throw new RuntimeException('Invalid state');
        }
        return $this->key;
    }

    /**
     * Move forward to next element.
     */
    public function next(): void
    {
        $this->valid = $this->iterator->valid();

        if (!$this->valid) {
            return;
        }

        $this->key = $this->iterator->key();
        $this->current = $this->iterator->current();

        $this->iterator->next();
    }

    /**
     * Rewind the iterator to the first element.
     */
    public function rewind(): void
    {
        $this->iterator->rewind();
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
        return $this->iterator->valid();
    }

    /**
     * Get next value.
     * @return T
     */
    public function getNext(): mixed
    {
        $current = $this->iterator->current();
        if ($current === null) {
            throw new RuntimeException('Invalid state');
        }
        return $current;
    }

    /**
     * Get next key.
     * @return I
     */
    public function getNextKey(): mixed
    {
        $key = $this->iterator->key();
        if ($key === null) {
            throw new RuntimeException('Invalid state');
        }
        return $key;
    }
}
