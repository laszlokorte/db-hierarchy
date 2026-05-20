<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

class Insert
{
    public function __construct(
        private Identifier $table,
        private array $columns,
        private array|Select $rows,
    ) {
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function getRows()
    {
        return $this->rows;
    }
}
