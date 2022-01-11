<?php declare(strict_types=1);

namespace Hoa\Compiler\Llk\Rules;

class RuleCounter
{
    private int $count = 0;

    public function next(): int
    {
        return $this->count++;
    }
}
