<?php

namespace App\Hierarchy\Schema\Definition;

class OrderDefinition
{
    private $columnName;

    public function __construct(?string $columnName = null, private OrderDirection $direction = OrderDirection::ASC, private bool $singleton = false)
    {
        $this->columnName = $columnName ?: ($singleton ? 'singleton' : 'order');
    }

    public function getColumnName(): ?string
    {
        return $this->columnName;
    }

    public function getDirection(): OrderDirection
    {
        return $this->direction;
    }

    public function isSingleton(): bool
    {
        return $this->singleton;
    }
}
