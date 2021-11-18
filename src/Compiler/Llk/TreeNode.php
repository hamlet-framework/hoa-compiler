<?php declare(strict_types=1);

namespace Hoa\Compiler\Llk;

use Hoa\Compiler\Visitor\Element;
use Hoa\Compiler\Visitor\Visit;

/**
 * @implements Element<TreeNode,string>
 */
class TreeNode implements Element
{
    /**
     * ID (should be something like #ruleName or token).
     */
    protected string $_id;

    /**
     * Value of the node (non-null for token nodes).
     * @var ?array<string>
     */
    protected ?array $_value = null;

    /**
     * Children.
     * @var array<TreeNode>
     */
    protected array $_children;

    /**
     * Parent.
     */
    protected ?TreeNode $_parent = null;

    /**
     * Attached data.
     */
    protected array $_data = [];

    /**
     * @param string $id
     * @param ?array<string> $value
     * @param array<TreeNode> $children
     * @param ?TreeNode $parent
     */
    public function __construct(string $id, ?array $value = null, array $children = [], ?TreeNode $parent = null)
    {
        $this->_id = $id;
        if (!empty($value)) {
            $this->_value = $value;
        }
        $this->_children = $children;
        if (null !== $parent) {
            $this->_parent = $parent;
        }
    }

    public function setId(string $id): string
    {
        $old = $this->_id;
        $this->_id = $id;
        return $old;
    }

    public function getId(): string
    {
        return $this->_id;
    }

    public function setValue(array $value): ?array
    {
        $old = $this->_value;
        $this->_value = $value;
        return $old;
    }

    public function getValue(): ?array
    {
        return $this->_value;
    }

    public function getValueToken(): ?string
    {
        return $this->_value['token'] ?? null;
    }

    public function getValueValue(): ?string
    {
        return $this->_value['value'] ?? null;
    }

    /**
     * Check if the node represents a token or not.
     */
    public function isToken(): bool
    {
        return !empty($this->_value);
    }

    public function prependChild(TreeNode $child): static
    {
        array_unshift($this->_children, $child);
        return $this;
    }

    public function appendChild(TreeNode $child): static
    {
        $this->_children[] = $child;
        return $this;
    }

    /**
     * @param array<TreeNode> $children
     * @return array<TreeNode>
     */
    public function setChildren(array $children): array
    {
        $old = $this->_children;
        $this->_children = $children;
        return $old;
    }

    public function getChild(int $i): ?TreeNode
    {
        return $this->childExists($i) ? $this->_children[$i] : null;
    }

    public function getChildren(): array
    {
        return $this->_children;
    }

    public function getChildrenNumber(): int
    {
        return count($this->_children);
    }

    public function childExists(int $i): bool
    {
        return array_key_exists($i, $this->_children);
    }

    public function setParent(TreeNode $parent): ?TreeNode
    {
        $old = $this->_parent;
        $this->_parent = $parent;
        return $old;
    }

    public function getParent(): ?TreeNode
    {
        return $this->_parent;
    }

    public function &getData(): array
    {
        return $this->_data;
    }

    /**
     * @param Visit<TreeNode,string> $visitor
     * @return string
     */
    public function accept(Visit $visitor): string
    {
        return $visitor->visit($this);
    }

    /**
     * Remove circular reference to the parent (help the garbage collector).
     */
    public function __destruct()
    {
        unset($this->_parent);
    }
}
