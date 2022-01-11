<?php declare(strict_types=1);

namespace Hamlet\Compiler\Visitor;

use Hamlet\Compiler\Llk\TreeNode;

/**
 * @implements Visit<TreeNode,string>
 */
class Dump implements Visit
{
    /**
     * Indentation depth.
     */
    private int $depth = 0;

    /**
     * @param TreeNode $element
     * @return string
     * @psalm-suppress PossiblyNullOperand
     */
    public function visit(Element $element): string
    {
        $this->depth++;
        $out = str_repeat('>  ', $this->depth) . $element->getId();
        if (null !== $value = $element->getValue()) {
            $out .=
                '(' .
                ('default' !== $value['namespace']
                    ? $value['namespace'] . ':'
                    : '') .
                $value['token'] . ', ' .
                $value['value'] . ')';
        }
        $data = $element->getData();
        if (!empty($data)) {
            $out .= ' ' . $this->dumpData($data);
        }
        $out .= "\n";
        foreach ($element->getChildren() as $child) {
            $out .= $child->accept($this);
        }
        $this->depth--;
        return $out;
    }

    /**
     * @param string|array $data
     * @return string
     * @psalm-suppress MixedArgument
     * @psalm-suppress MixedAssignment
     */
    protected function dumpData(string|array $data): string
    {
        if (!is_array($data)) {
            return $data;
        }
        $out = '';
        foreach ($data as $key => $value) {
            $out .= '[' . $key . ' => ' . $this->dumpData($value) . ']';
        }
        return $out;
    }
}
