<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Operator\Comparison;

use App\Hierarchy\Storage\Relational\Algebra\Operator\BinaryInterface;

class Equal implements BinaryInterface
{
    public function __construct(private bool $allowNull = false)
    {
    }

    public function allowNull(): bool
    {
        return $this->allowNull;
    }
}
