<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;

class Join
{
    public function __construct(
        private TableReference $table,
        private ValueInterface $condition,
        private string $direction = 'INNER',
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

    public function getDirection()
    {
        return $this->direction;
    }
}
