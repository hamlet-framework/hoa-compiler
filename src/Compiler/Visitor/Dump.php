<?php declare(strict_types=1);

namespace Hoa\Compiler\Visitor;

use Hoa\Compiler\Llk\TreeNode;

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

    protected function dumpData(mixed $data): ?string
    {
        if (!is_array($data)) {
            return $data;
        }
        $out = null;
        foreach ($data as $key => $value) {
            $out .= '[' . $key . ' => ' . $this->dumpData($value) . ']';
        }
        return $out;
    }
}
