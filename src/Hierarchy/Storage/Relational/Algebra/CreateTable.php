<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

class CreateTable
{
    /**
     * @param array<int,mixed> $columns
     * @param array<int,mixed> $uniques
     * @param array<int,mixed> $foreignKeys
     */
    public function __construct(
        private Identifier $name,
        private TableColumn $primaryKey,
        private array $columns,
        private array $uniques,
        private array $foreignKeys,
    ) {
    }

    public function getName(): Identifier
    {
        return $this->name;
    }

    public function getPrimaryKey(): TableColumn
    {
        return $this->primaryKey;
    }

    /**
     * @return array<int,mixed>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @return array<int,mixed>
     */
    public function getUniques(): array
    {
        return $this->uniques;
    }

    /**
     * @return array<int,mixed>
     */
    public function getForeignKeys(): array
    {
        return $this->foreignKeys;
    }

    public function hasForeignKeys(): bool
    {
        return !empty($this->foreignKeys);
    }
}
