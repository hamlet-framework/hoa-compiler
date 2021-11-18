<?php declare(strict_types=1);

namespace Hoa\Compiler\Llk\Rules;

use Hoa\Compiler;

class Repetition extends Rule
{
    /**
     * Minimum bound.
     */
    protected int $_min = 0;

    /**
     * Maximum bound.
     */
    protected int $_max = 0;

    public function __construct(string|int $name, int $min, int $max, mixed $children, ?string $nodeId)
    {
        parent::__construct($name, $children, $nodeId);
        $min = max(0, $min);
        $max = max(-1, $max);

        if ($max !== -1 && $min > $max) {
            $message = sprintf('Cannot repeat with a min (%d) greater than max (%d).', $min, $max);
            throw new Compiler\Exceptions\RuleException($message);
        }

        $this->_min = $min;
        $this->_max = $max;
    }

    /**
     * Get minimum bound.
     */
    public function getMin(): int
    {
        return $this->_min;
    }

    /**
     * Get maximum bound.
     */
    public function getMax(): int
    {
        return $this->_max;
    }

    /**
     * Check whether the maximum repetition is unbounded.
     */
    public function isInfinite(): bool
    {
        return $this->getMax() === -1;
    }
}
