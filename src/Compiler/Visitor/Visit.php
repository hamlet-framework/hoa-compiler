<?php declare(strict_types=1);

namespace Hoa\Compiler\Visitor;

/**
 * @template E as Element
 * @template R
 */
interface Visit
{
    /**
     * @param E $element
     * @return R
     */
    public function visit(Element $element): mixed;
}
