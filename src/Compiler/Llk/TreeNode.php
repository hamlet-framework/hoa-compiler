<?php declare(strict_types=1);

namespace Hamlet\Compiler\Llk;

use Hamlet\Compiler\Visitor\Element;
use Hamlet\Compiler\Visitor\Visit;

/**
 * @implements Element<TreeNode,string>
 */
final class TreeNode implements Element
{
    /**
     * Attached data.
     */
    private array $data = [];

    /**
     * @param string $id Should be something like #ruleName or token
     * @param ?array<string,?string> $value Value of the node (non-null for token nodes).
     * @param array<TreeNode> $children
     * @param ?TreeNode $parent
     */
    public function __construct(
        private readonly string $id,
        private readonly ?array $value = null,
        private array $children = [],
        private ?TreeNode $parent = null
    ) {
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

    public function prependChild(TreeNode $child): void
    {
        array_unshift($this->children, $child);
    }

    public function appendChild(TreeNode $child): void
    {
        $this->children[] = $child;
    }

    public function getChild(int $i): ?TreeNode
    {
        return $this->children[$i] ?? null;
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
