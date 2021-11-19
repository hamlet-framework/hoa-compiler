<?php declare(strict_types=1);

namespace Hoa\Compiler\Llk\Rules;

abstract class Rule
{
    /**
     * RuleException name.
     */
    protected string|int $_name;

    /**
     * RuleException's children. Can be an array of names or a single name.
     * @var array<Rule|Invocation|string|int>|string|int|null
     */
    protected array|string|int|null $_children;

    /**
     * Node ID.
     */
    protected ?string $_nodeId = null;

    /**
     * Node options.
     */
    protected array $_nodeOptions = [];

    /**
     * Default ID.
     */
    protected ?string $_defaultId = null;

    /**
     * Default options.
     * @var array<string>
     */
    protected array $_defaultOptions = [];

    /**
     * For non-transitional rule: PP representation.
     */
    protected ?string $_pp = null;

    /**
     * Whether the rule is transitional or not (i.e. not declared in the grammar but created by the analyzer).
     */
    protected bool $_transitional = true;

    /**
     * @param string|int $name
     * @param array<Rule|Invocation|string|int>|string|int|null $children
     * @param string|null $nodeId
     */
    public function __construct(string|int $name, array|string|int|null $children, string|null $nodeId = null)
    {
        $this->_name = $name;
        $this->_children = $children;
        $this->setNodeId($nodeId);
    }

    public function setName(string $name): string|int
    {
        $old = $this->_name;
        $this->_name = $name;
        return $old;
    }

    public function getName(): string|int
    {
        return $this->_name;
    }

    /**
     * @param array<Rule|Invocation|string|int>|string|int|null $children
     * @return array<Rule|Invocation|string|int>|string|int|null
     * @psalm-suppress MismatchingDocblockParamType
     */
    protected function setChildren(array|string|int $children): array|string|int|null
    {
        $old = $this->_children;
        $this->_children = $children;
        return $old;
    }

    /**
     * @return array<Rule|Invocation|string|int>|string|int|null
     */
    public function getChildren(): array|string|int|null
    {
        return $this->_children;
    }

    public function setNodeId(?string $nodeId): ?string
    {
        $old = $this->_nodeId;

        if ($nodeId && false !== $pos = strpos($nodeId, ':')) {
            $this->_nodeId = substr($nodeId, 0, $pos);
            $this->_nodeOptions = str_split(substr($nodeId, $pos + 1));
        } else {
            $this->_nodeId = $nodeId;
            $this->_nodeOptions = [];
        }

        return $old;
    }

    public function getNodeId(): ?string
    {
        return $this->_nodeId;
    }

    public function getNodeOptions(): array
    {
        return $this->_nodeOptions;
    }

    public function setDefaultId(string $defaultId): ?string
    {
        $old = $this->_defaultId;

        if (false !== $pos = strpos($defaultId, ':')) {
            $this->_defaultId = substr($defaultId, 0, $pos);
            $this->_defaultOptions = str_split(substr($defaultId, $pos + 1));
        } else {
            $this->_defaultId = $defaultId;
            $this->_defaultOptions = [];
        }

        return $old;
    }

    public function getDefaultId(): ?string
    {
        return $this->_defaultId;
    }

    /**
     * @return array<string>
     */
    public function getDefaultOptions(): array
    {
        return $this->_defaultOptions;
    }

    public function setPPRepresentation(string $pp): ?string
    {
        $old = $this->_pp;
        $this->_pp = $pp;
        $this->_transitional = false;
        return $old;
    }

    public function getPPRepresentation(): ?string
    {
        return $this->_pp;
    }

    public function isTransitional(): bool
    {
        return $this->_transitional;
    }
}
