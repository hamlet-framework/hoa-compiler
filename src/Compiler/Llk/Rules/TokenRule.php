<?php declare(strict_types=1);

namespace Hoa\Compiler\Llk\Rules;

use Hoa\Compiler;
use Hoa\Compiler\Exceptions\Exception;
use Hoa\Compiler\Llk\Llk;
use Hoa\Compiler\Llk\Parser;
use Hoa\Compiler\Llk\TreeNode;

final class TokenRule extends Rule
{
    /**
     * LL(k) compiler of hoa://Library/Regex/Regex.pp.
     */
    protected static ?Parser $regexCompiler = null;

    protected ?string $namespace = null;

    protected ?string $regex = null;

    /**
     * AST of the regex.
     */
    protected ?TreeNode $ast = null;

    /**
     * TokenRule value.
     */
    protected ?string $value = null;

    /**
     * @param string|int $name Name.
     * @param string $tokenName TokenRule name.
     * @param string|null $nodeId Node ID.
     * @param int $unification Unification index.
     * @param bool $kept Whether the token is kept or not in the AST.
     */
    public function __construct(
        string|int $name,
        protected string $tokenName,
        ?string $nodeId,
        protected int $unification,
        protected bool $kept = false
    ) {
        parent::__construct($name, [], $nodeId);
    }

    public function getTokenName(): string
    {
        return $this->tokenName;
    }

    public function setNamespace(string $namespace): void
    {
        $this->namespace = $namespace;
    }

    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    public function setRepresentation(string $regex): void
    {
        $this->regex = $regex;
    }

    public function getRepresentation(): string
    {
        if (!$this->regex) {
            throw new Exception('Not initialized.');
        }
        return $this->regex;
    }

    /**
     * Get AST of the token representation.
     */
    public function getAST(): TreeNode
    {
        if (!TokenRule::$regexCompiler) {
            TokenRule::$regexCompiler = Llk::load(__DIR__ . '/../../../Grammars/Regex.pp');
        }
        if (!$this->ast) {
            $this->ast = TokenRule::$regexCompiler->parse($this->getRepresentation());
        }
        return $this->ast;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    /**
     * Check whether the token is kept in the AST or not.
     */
    public function isKept(): bool
    {
        return $this->kept;
    }

    public function getUnificationIndex(): int
    {
        return $this->unification;
    }
}
