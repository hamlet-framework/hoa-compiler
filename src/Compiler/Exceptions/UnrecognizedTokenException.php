<?php declare(strict_types=1);

namespace Hoa\Compiler\Exceptions;

class UnrecognizedTokenException extends Exception
{
    protected int $column = 0;

    public function __construct(string $message, int $code, int $line, int $column)
    {
        parent::__construct($message, $code);
        $this->line = $line;
        $this->column = $column;
    }

    public function getColumn(): int
    {
        return $this->column;
    }
}
