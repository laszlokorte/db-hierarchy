<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;

class Select
{
    /**
     * @param array<int,mixed> $projections
     * @param array<int,mixed> $tableNames
     * @param array<int,mixed> $joins
     * @param array<int,mixed> $orders
     * @param array<int,mixed> $unions
     */
    public function __construct(private array $projections, private array $tableNames = [], private array $joins = [], private ?ValueInterface $condition = null, private array $orders = [], private ?int $limit = null, private int $offset = 0, private ?array $groupings = null, private ?ValueInterface $having = null, private array $unions = [])
    {
    }

    /**
     * @return array<int,mixed>
     */
    public function getProjections(): array
    {
        return $this->projections;
    }

    /**
     * @return array<int,mixed>
     */
    public function getTables(): array
    {
        return $this->tableNames;
    }

    /**
     * @return array<int,mixed>
     */
    public function getJoins(): array
    {
        return $this->joins;
    }

    public function getCondition(): ?ValueInterface
    {
        return $this->condition;
    }

    /**
     * @return array<int,mixed>
     */
    public function getOrders(): array
    {
        return $this->orders;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getGroupings(): ?array
    {
        return $this->groupings;
    }

    public function getHaving(): ?ValueInterface
    {
        return $this->having;
    }

    /**
     * @return array<int,mixed>
     */
    public function getUnions(): array
    {
        return $this->unions;
    }
}
