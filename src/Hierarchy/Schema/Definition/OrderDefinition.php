<?php

namespace App\Hierarchy\Schema\Definition;

class OrderDefinition
{
    private $columnName;
    /**
     * @param mixed $columnName
     * @param mixed $direction
     */
    public function __construct(
        $columnName = null,
        private $direction = 'ASC',
        private bool $singleton = false,
    ) {
        $this->columnName = $columnName ?: ($singleton ? 'singleton' : 'order');
    }

    public function getColumnName() : ?string
    {
        return $this->columnName;
    }

    public function getDirection() : string
    {
        return $this->direction;
    }

    public function isSingleton() : bool
    {
        return $this->singleton;
    }
}
