<?php declare(strict_types=1);

namespace Hoa\Compiler\Visitor;

/**
 * @template E as Element
 * @template R
 */
interface Element
{
    /**
     * @param Visit<E,R> $visitor
     * @return R
     */
    public function accept(Visit $visitor): mixed;
}
