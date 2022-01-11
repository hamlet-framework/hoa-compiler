<?php declare(strict_types=1);

namespace Hamlet\Compiler\Llk\Rules;

abstract class InvocationRule
{
    /**
     * Whether the rule is transitional or not (i.e. not declared in the grammar but created by the analyzer).
     */
    protected bool $transitional;

    /**
     * @param int|string $name
     * @param int|string $data
     * @param array<InvocationRule> $todo Piece of todo sequence.
     * @param int $depth Depth in the trace.
     */
    public function __construct(
        protected int|string $name,
        protected int|string $data,
        protected array      $todo = [],
        protected int        $depth = -1
    ) {
        $this->transitional = is_int($name);
    }

    public function getName(): string|int
    {
        return $this->name;
    }

    public function getData(): string|int
    {
        return $this->data;
    }

    /**
     * Get todo sequence.
     * @return array<InvocationRule>
     */
    public function getTodo(): array
    {
        return $this->todo;
    }

    /**
     * Set depth in trace.
     */
    public function setDepth(int $depth): void
    {
        $this->depth = $depth;
    }

    /**
     * Get depth in trace.
     */
    public function getDepth(): int
    {
        return $this->depth;
    }

    /**
     * Check whether the rule is transitional or not.
     */
    public function isTransitional(): bool
    {
        return $this->transitional;
    }
}
