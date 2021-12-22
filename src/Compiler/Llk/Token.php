<?php

namespace Hoa\Compiler\Llk;

final class Token
{
    public function __construct(
        public readonly string $token,
        public readonly string $value,
        public readonly int $length,
        public readonly string $namespace,
        public readonly bool $keep,
        public readonly int $offset,
    ) {
    }

    public static function eof(int $offset): Token
    {
        return new Token('EOF', 'EOF', 0, 'default', true, $offset);
    }

    public function toArray(): array
    {
        return [
            'token'     => $this->token,
            'value'     => $this->value,
            'length'    => $this->length,
            'namespace' => $this->namespace,
            'keep'      => $this->keep,
            'offset'    => $this->offset,
        ];
    }
}
