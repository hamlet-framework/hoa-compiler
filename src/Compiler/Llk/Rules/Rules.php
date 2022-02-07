<?php declare(strict_types=1);

namespace Hamlet\Compiler\Llk\Rules;

use ArrayIterator;
use Iterator;
use RuntimeException;

class Rules
{
    /**
     * @param array<Rule> $rules
     */
    public function __construct(private array $rules)
    {
    }

    public function contains(string|int $ruleName): bool
    {
        return array_key_exists($ruleName, $this->rules);
    }

    public function get(string|int $ruleName): Rule
    {
        if (!$this->contains($ruleName)) {
            throw new RuntimeException('Rule not found: ' . $ruleName);
        }
        return $this->rules[$ruleName];
    }

    public function root(): int|string
    {
        foreach ($this->rules as $ruleName => $_) {
            if (!is_int($ruleName)) {
                return $ruleName;
            }
        }
        throw new RuntimeException('Cannot find root rule');
    }

    /**
     * @return Iterator<int|string,Rule>
     */
    public function iterator(): Iterator
    {
        return new ArrayIterator($this->rules);
    }
}
