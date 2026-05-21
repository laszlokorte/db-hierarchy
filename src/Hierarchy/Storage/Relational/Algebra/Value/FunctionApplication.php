<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Value;

use App\Hierarchy\Storage\Relational\Algebra\Operator\FunctionInterface;

class FunctionApplication implements ValueInterface
{
    /**
     * @param array<int,mixed> $arguments
     */
    public function __construct(
        private FunctionInterface $function,
        private array $arguments,
    ) {
    }

    public function getFunction(): FunctionInterface
    {
        return $this->function;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }
}
