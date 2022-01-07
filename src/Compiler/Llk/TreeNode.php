<?php declare(strict_types=1);

namespace Hoa\Compiler\Llk;

use Hoa\Compiler\Visitor\Element;
use Hoa\Compiler\Visitor\Visit;

/**
 * @implements Element<TreeNode,string>
 */
final class TreeNode implements Element
{
    /**
     * Value of the node (non-null for token nodes).
     * @var ?array<string,?string>
     */
    protected ?array $value = null;

    /**
     * Parent.
     */
    protected ?TreeNode $parent = null;

    /**
     * Attached data.
     */
    protected array $data = [];

    /**
     * @param string $id Should be something like #ruleName or token
     * @param ?array<string,?string> $value
     * @param array<TreeNode> $children
     * @param ?TreeNode $parent
     */
    public function __construct(private string $id, ?array $value = null, private array $children = [], ?TreeNode $parent = null)
    {
        if (!empty($value)) {
            $this->value = $value;
        }
        if (null !== $parent) {
            $this->parent = $parent;
        }
    }

    public function setId(string $id): string
    {
        $old = $this->id;
        $this->id = $id;
        return $old;
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return ?array<string,?string>
     */
    public function getValue(): ?array
    {
        return $this->value;
    }

    public function getValueToken(): ?string
    {
        return $this->value['token'] ?? null;
    }

    public function getValueValue(): ?string
    {
        return $this->value['value'] ?? null;
    }

    /**
     * Check if the node represents a token or not.
     */
    public function isToken(): bool
    {
        return !empty($this->value);
    }

    public function prependChild(TreeNode $child): TreeNode
    {
        array_unshift($this->children, $child);
        return $this;
    }

    public function appendChild(TreeNode $child): TreeNode
    {
        $this->children[] = $child;
        return $this;
    }

    /**
     * @param array<TreeNode> $children
     * @return array<TreeNode>
     */
    public function setChildren(array $children): array
    {
        $old = $this->children;
        $this->children = $children;
        return $old;
    }

    public function getChild(int $i): ?TreeNode
    {
        return $this->childExists($i) ? $this->children[$i] : null;
    }

    /**
     * @return array<TreeNode>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    public function getChildrenNumber(): int
    {
        return count($this->children);
    }

    public function childExists(int $i): bool
    {
        return array_key_exists($i, $this->children);
    }

    public function setParent(TreeNode $parent): void
    {
        $this->parent = $parent;
    }

    public function getParent(): ?TreeNode
    {
        return $this->parent;
    }

    public function &getData(): array
    {
        return $this->data;
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
        unset($this->parent);
    }
}
