<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

class CreateTable
{
    public function __construct(
        private Identifier $name,
        private TableColumn $primaryKey,
        private array $columns,
        private array $uniques,
        private array $foreignKeys,
    ) {
    }

    public function getName()
    {
        return $this->name;
    }

    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function getUniques()
    {
        return $this->uniques;
    }

    public function getForeignKeys()
    {
        return $this->foreignKeys;
    }

    public function hasForeignKeys()
    {
        return !empty($this->foreignKeys);
    }
}
