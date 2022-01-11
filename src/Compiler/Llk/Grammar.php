<?php declare(strict_types=1);

namespace Hamlet\Compiler\Llk;

class Grammar
{
    /**
     * @param array<string,array<string,string>> $tokens
     * @param array<string,string> $rawRules
     * @param array<string,bool|int|string> $pragmas
     */
    public function __construct(
        private array $tokens,
        private array $rawRules,
        private array $pragmas,
    ) {
    }

    /**
     * @return array<string,array<string,string>>
     */
    public function tokens(): array
    {
        return $this->tokens;
    }

    /**
     * @return array<string,string>
     */
    public function rawRules(): array
    {
        return $this->rawRules;
    }

    /**
     * @return array<string,bool|int|string>
     */
    public function pragmas(): array
    {
        return $this->pragmas;
    }
}
