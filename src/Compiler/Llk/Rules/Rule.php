<?php declare(strict_types=1);

namespace Hoa\Compiler\Llk\Rules;

/**
 * @template C
 */
abstract class Rule
{
    /**
     * RuleException's children. Can be an array of names or a single name.
     * @var C
     */
    protected array|string|int|null $children;

    /**
     * Node ID.
     */
    protected ?string $nodeId = null;

    /**
     * Node options.
     * @var array<string>
     */
    protected array $nodeOptions = [];

    /**
     * Default ID.
     */
    protected ?string $defaultId = null;

    /**
     * Default options.
     * @var array<string>
     */
    protected array $defaultOptions = [];

    /**
     * For non-transitional rule: PP representation.
     */
    protected ?string $pp = null;

    /**
     * Whether the rule is transitional or not (i.e. not declared in the grammar but created by the analyzer).
     */
    protected bool $transitional = true;

    /**
     * @param string|int $name
     * @param C $children
     * @param ?string $nodeId
     */
    public function __construct(protected string|int $name, mixed $children, ?string $nodeId = null)
    {
        $this->children = $children;
        $this->setNodeId($nodeId);
    }

    public function setName(string $name): string|int
    {
        $old = $this->name;
        $this->name = $name;
        return $old;
    }

    public function getName(): string|int
    {
        return $this->name;
    }

    /**
     * @param C $children
     * @return C
     */
    protected function setChildren(mixed $children): mixed
    {
        $old = $this->children;
        $this->children = $children;
        return $old;
    }

    /**
     * @return C
     */
    public function getChildren(): mixed
    {
        return $this->children;
    }

    public function setNodeId(?string $nodeId): ?string
    {
        $old = $this->nodeId;

        if ($nodeId && ($pos = strpos($nodeId, ':')) !== false) {
            $this->nodeId = substr($nodeId, 0, $pos);
            $this->nodeOptions = str_split(substr($nodeId, $pos + 1));
        } else {
            $this->nodeId = $nodeId;
            $this->nodeOptions = [];
        }

        return $old;
    }

    public function getNodeId(): ?string
    {
        return $this->nodeId;
    }

    public function getNodeOptions(): array
    {
        return $this->nodeOptions;
    }

    public function setDefaultId(string $defaultId): ?string
    {
        $old = $this->defaultId;

        if (($pos = strpos($defaultId, ':')) !== false) {
            $this->defaultId = substr($defaultId, 0, $pos);
            $this->defaultOptions = str_split(substr($defaultId, $pos + 1));
        } else {
            $this->defaultId = $defaultId;
            $this->defaultOptions = [];
        }

        return $old;
    }

    public function getDefaultId(): ?string
    {
        return $this->defaultId;
    }

    /**
     * @return array<string>
     */
    public function getDefaultOptions(): array
    {
        return $this->defaultOptions;
    }

    public function setPPRepresentation(string $pp): ?string
    {
        $old = $this->pp;
        $this->pp = $pp;
        $this->transitional = false;
        return $old;
    }

    public function getPPRepresentation(): ?string
    {
        return $this->pp;
    }

    public function isTransitional(): bool
    {
        return $this->transitional;
    }
}
