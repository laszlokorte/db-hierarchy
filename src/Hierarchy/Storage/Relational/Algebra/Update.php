<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;

class Update
{
    /**
     * @param array<int,mixed> $setters
     */
    public function __construct(private TableReference $table, private array $setters, private ?ValueInterface $condition = null, private ?Select $selection = null)
    {
    }

    public function getTable(): TableReference
    {
        return $this->table;
    }

    /**
     * @return array<int,mixed>
     */
    public function getSetters(): array
    {
        return $this->setters;
    }

    public function getCondition(): ?ValueInterface
    {
        return $this->condition;
    }

    public function getSelect(): ?Select
    {
        return $this->selection;
    }

    public function isEmpty(): bool
    {
        return empty($this->setters);
    }
}
