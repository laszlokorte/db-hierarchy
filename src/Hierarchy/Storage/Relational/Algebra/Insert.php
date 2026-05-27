<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

class Insert
{
    /**
     * @param array<int,mixed> $columns
     * @param mixed[]|Select   $rows
     */
    public function __construct(
        private Identifier $table,
        private array $columns,
        private array|Select $rows,
    ) {
    }

    public function getTable(): Identifier
    {
        return $this->table;
    }

    /**
     * @return array<int,mixed>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @return mixed[]|Select
     */
    public function getRows(): array|Select
    {
        return $this->rows;
    }
}
