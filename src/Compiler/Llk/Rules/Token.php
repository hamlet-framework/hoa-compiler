<?php declare(strict_types=1);

namespace Hoa\Compiler\Llk\Rules;

use Hoa\Compiler;
use Hoa\Compiler\Llk\Parser;
use Hoa\Compiler\Llk\TreeNode;

class Token extends Rule
{
    /**
     * LL(k) compiler of hoa://Library/Regex/Regex.pp.
     */
    protected static ?Parser $_regexCompiler = null;

    protected string $_tokenName;

    protected ?string $_namespace = null;

    protected ?string $_regex = null;

    /**
     * AST of the regex.
     */
    protected ?TreeNode $_ast = null;

    /**
     * Token value.
     */
    protected ?string $_value = null;

    /**
     * Whether the token is kept or not in the AST.
     */
    protected bool $_kept = false;

    /**
     * Unification index.
     */
    protected int $_unification = -1;

    /**
     * @param string|int $name Name.
     * @param string $tokenName Token name.
     * @param string|null $nodeId Node ID.
     * @param int $unification Unification index.
     * @param bool $kept Whether the token is kept or not in the AST.
     */
    public function __construct(string|int $name, string $tokenName, ?string $nodeId, int $unification, bool $kept = false)
    {
        parent::__construct($name, null, $nodeId);

        $this->_tokenName = $tokenName;
        $this->_unification = $unification;
        $this->_kept = $kept;
    }

    public function getTokenName(): string
    {
        return $this->_tokenName;
    }

    public function setNamespace(string $namespace): ?string
    {
        $old = $this->_namespace;
        $this->_namespace = $namespace;
        return $old;
    }

    public function getNamespace(): ?string
    {
        return $this->_namespace;
    }

    public function setRepresentation(string $regex): ?string
    {
        $old = $this->_regex;
        $this->_regex = $regex;
        return $old;
    }

    public function getRepresentation(): ?string
    {
        return $this->_regex;
    }

    /**
     * Get AST of the token representation.
     * @throws Compiler\Exceptions\UnexpectedTokenException
     */
    public function getAST(): ?TreeNode
    {
        if (null === static::$_regexCompiler) {
            static::$_regexCompiler = Compiler\Llk\Llk::load(__DIR__ . '/../../../Grammars/Regex.pp');
        }

        if (null === $this->_ast) {
            $representation = $this->getRepresentation();
            assert($representation !== null);
            $this->_ast = static::$_regexCompiler->parse($representation);
        }

        return $this->_ast;
    }

    public function setValue(string $value): ?string
    {
        $old = $this->_value;
        $this->_value = $value;
        return $old;
    }

    /**
     * Get token value.
     */
    public function getValue(): ?string
    {
        return $this->_value;
    }

    /**
     * Set whether the token is kept or not in the AST.
     */
    public function setKept(bool $kept): bool
    {
        $old = $this->_kept;
        $this->_kept = $kept;
        return $old;
    }

    /**
     * Check whether the token is kept in the AST or not.
     */
    public function isKept(): bool
    {
        return $this->_kept;
    }

    public function getUnificationIndex(): int
    {
        return $this->_unification;
    }
}
