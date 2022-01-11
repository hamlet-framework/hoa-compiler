<?php

namespace Hamlet\Compiler\Llk;

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

    /**
     * @param array{token:string,value:string,length:int,namespace:string,keep:bool,offset:int} $array
     * @return Token
     */
    public static function fromArray(array $array): Token
    {
        return new Token(
            $array['token'],
            $array['value'],
            $array['length'],
            $array['namespace'],
            $array['keep'],
            $array['offset']
        );
    }

    /**
     * @return array{token:string,value:string,length:int,namespace:string,keep:bool,offset:int}
     */
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
