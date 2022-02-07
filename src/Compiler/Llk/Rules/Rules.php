<?php declare(strict_types=1);

namespace Hamlet\Compiler\Llk\Rules;

use ArrayIterator;
use IteratorAggregate;
use RuntimeException;
use Traversable;

final class Rules implements IteratorAggregate
{
    /**
     * @param array<Rule> $rules an associative array, name(which can be numerical) => Rule object
     */
    public function __construct(private array $rules)
    {
    }

    public static function createEmpty(): Rules
    {
        return new self([]);
    }

    public function contains(string|int $ruleName): bool
    {
        return array_key_exists($ruleName, $this->rules);
    }

    public function get(string|int $ruleName): Rule
    {
        if (!$this->contains($ruleName)) {
            throw new RuntimeException(sprintf('Rule not found: "%s"', $ruleName));
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
        throw new RuntimeException('Root rule not found');
    }

    public function remove(string|int $ruleName): void
    {
        unset($this->rules[$ruleName]);
    }

    public function set(string|int $ruleName, Rule $rule): void
    {
        $this->rules[$ruleName] = $rule;
    }

    /**
     * @return Traversable<Rule>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->rules);
    }
}
