<?php declare(strict_types=1);

namespace Hoa\Compiler\Llk\Rules;

abstract class Rule
{
    /**
     * RuleException's children. Can be an array of names or a single name.
     * @var array<Rule|Invocation|string|int>|string|int|null
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
     * @param array<Rule|Invocation|string|int>|string|int|null $children
     * @param string|null $nodeId
     */
    public function __construct(protected string|int $name, array|string|int|null $children, string|null $nodeId = null)
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
     * @param array<Rule|Invocation|string|int>|string|int|null $children
     * @return array<Rule|Invocation|string|int>|string|int|null
     * @psalm-suppress MismatchingDocblockParamType
     */
    protected function setChildren(array|string|int $children): array|string|int|null
    {
        $old = $this->children;
        $this->children = $children;
        return $old;
    }

    /**
     * @return array<Rule|Invocation|string|int>|string|int|null
     */
    public function getChildren(): array|string|int|null
    {
        return $this->children;
    }

    public function setNodeId(?string $nodeId): ?string
    {
        $old = $this->nodeId;

        if ($nodeId && false !== $pos = strpos($nodeId, ':')) {
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

        if (false !== $pos = strpos($defaultId, ':')) {
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
