<?php declare(strict_types=1);

namespace Hoa\Compiler\Llk\Rules;

abstract class Invocation
{
    /**
     * RuleException.
     */
    protected string|int|null $_rule = null;

    /**
     * Data.
     */
    protected mixed $_data = null;

    /**
     * Piece of todo sequence.
     */
    protected ?array $_todo = null;

    /**
     * Depth in the trace.
     */
    protected int $_depth = -1;

    /**
     * Whether the rule is transitional or not (i.e. not declared in the grammar but created by the analyzer).
     */
    protected bool $_transitional = false;

    public function __construct(string|int $rule, mixed $data, array $todo = null, int $depth = -1)
    {
        $this->_rule = $rule;
        $this->_data = $data;
        $this->_todo = $todo;
        $this->_depth = $depth;
        $this->_transitional = is_int($rule);
    }

    /**
     * Get rule name.
     */
    public function getRule(): string|int|null
    {
        return $this->_rule;
    }

    /**
     * Get data.
     */
    public function getData(): mixed
    {
        return $this->_data;
    }

    /**
     * Get todo sequence.
     */
    public function getTodo(): ?array
    {
        return $this->_todo;
    }

    /**
     * Set depth in trace.
     */
    public function setDepth(int $depth): int
    {
        $old = $this->_depth;
        $this->_depth = $depth;
        return $old;
    }

    /**
     * Get depth in trace.
     */
    public function getDepth(): int
    {
        return $this->_depth;
    }

    /**
     * Check whether the rule is transitional or not.
     */
    public function isTransitional(): bool
    {
        return $this->_transitional;
    }
}
