<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Value;

use App\Hierarchy\Storage\Relational\Algebra\Projection;

class Projected implements ValueInterface
{
    public function __construct(
        private Projection $projection,
    ) {
    }

    public function getProjection()
    {
        return $this->projection;
    }
}
