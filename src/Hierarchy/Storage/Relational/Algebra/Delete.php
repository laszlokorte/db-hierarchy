<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;

class Delete
{
    public function __construct(
        private TableReference $table,
        private ValueInterface $condition,
    ) {
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getCondition()
    {
        return $this->condition;
    }
}
