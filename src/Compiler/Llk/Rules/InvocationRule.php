<?php declare(strict_types=1);

namespace Hoa\Compiler\Llk\Rules;

abstract class InvocationRule
{
    /**
     * Whether the rule is transitional or not (i.e. not declared in the grammar but created by the analyzer).
     */
    protected bool $transitional;

    /**
     * @param string|int $rule Rule.
     * @param int|string $data Data.
     * @param array<InvocationRule> $todo Piece of todo sequence.
     * @param int $depth Depth in the trace.
     */
    public function __construct(
        protected string|int $rule,
        protected int|string $data,
        protected array $todo = [],
        protected int $depth = -1
    ) {
        $this->transitional = is_int($rule);
    }

    /**
     * Get rule name.
     */
    public function getRule(): string|int
    {
        return $this->rule;
    }

    /**
     * Get data.
     */
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
    public function setDepth(int $depth): int
    {
        $old = $this->depth;
        $this->depth = $depth;
        return $old;
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
