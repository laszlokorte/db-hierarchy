<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Operator\Function;

use App\Hierarchy\Storage\Relational\Algebra\Operator\FunctionInterface;

class NamedFunction implements FunctionInterface
{
    public function __construct(private string $name, private int $expectedArity)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }
}
