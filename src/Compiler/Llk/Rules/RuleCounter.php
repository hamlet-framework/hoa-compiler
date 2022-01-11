<?php declare(strict_types=1);

namespace Hamlet\Compiler\Llk\Rules;

class RuleCounter
{
    private int $count = 0;

    public function next(): int
    {
        return $this->count++;
    }
}
