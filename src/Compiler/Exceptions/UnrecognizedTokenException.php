<?php declare(strict_types=1);

namespace Hoa\Compiler\Exceptions;

class UnrecognizedTokenException extends Exception
{
    public function __construct(string $message, int $code, int $line, protected int $column)
    {
        parent::__construct($message, $code);
        $this->line = $line;
    }

    public function getColumn(): int
    {
        return $this->column;
    }
}
