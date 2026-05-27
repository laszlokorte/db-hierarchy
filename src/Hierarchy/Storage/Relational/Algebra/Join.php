<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;

class Join
{
    public function __construct(
        private TableReference $table,
        private ValueInterface $condition,
        private JoinDirection $direction = JoinDirection::INNER,
    ) {
    }

    public function getTable(): TableReference
    {
        return $this->table;
    }

    public function getCondition(): ValueInterface
    {
        return $this->condition;
    }

    public function getDirection(): JoinDirection
    {
        return $this->direction;
    }
}
