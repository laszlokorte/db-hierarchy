<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Value;

use App\Hierarchy\Storage\Relational\Algebra\Identifier;
use App\Hierarchy\Storage\Relational\Algebra\TableReference;

class ColumnReference implements ValueInterface
{
    public function __construct(
        private TableReference $table,
        private Identifier $name,
    ) {
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getName()
    {
        return $this->name;
    }
}
