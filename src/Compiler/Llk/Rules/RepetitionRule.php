<?php declare(strict_types=1);

namespace Hoa\Compiler\Llk\Rules;

use Hoa\Compiler;

/**
 * @template C as array<Rule|InvocationRule|string>|string|int
 * @extends Rule<C>
 */
class RepetitionRule extends Rule
{
    /**
     * Minimum bound.
     */
    protected int $min = 0;

    /**
     * Maximum bound.
     */
    protected int $max = 0;

    /**
     * @param string|int $name
     * @param int $min
     * @param int $max
     * @param C $children
     * @param string|null $nodeId
     */
    public function __construct(string|int $name, int $min, int $max, array|string|int $children, ?string $nodeId)
    {
        parent::__construct($name, $children, $nodeId);
        $min = max(0, $min);
        $max = max(-1, $max);

        if ($max !== -1 && $min > $max) {
            $message = sprintf('Cannot repeat with a min (%d) greater than max (%d).', $min, $max);
            throw new Compiler\Exceptions\RuleException($message);
        }

        $this->min = $min;
        $this->max = $max;
    }

    /**
     * Get minimum bound.
     */
    public function getMin(): int
    {
        return $this->min;
    }

    /**
     * Get maximum bound.
     */
    public function getMax(): int
    {
        return $this->max;
    }

    /**
     * Check whether the maximum repetition is unbounded.
     */
    public function isInfinite(): bool
    {
        return $this->max == -1;
    }
}
