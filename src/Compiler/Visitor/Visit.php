<?php declare(strict_types=1);

namespace Hamlet\Compiler\Visitor;

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
