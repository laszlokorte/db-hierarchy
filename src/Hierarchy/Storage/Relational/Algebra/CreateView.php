<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

class CreateView
{
    public function __construct(
        private Identifier $name,
        private Select $query,
    ) {
    }

    public function getName(): Identifier
    {
        return $this->name;
    }

    public function getQuery(): Select
    {
        return $this->query;
    }
}
