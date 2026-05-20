<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Windowing;

class RankWindow implements WindowingInterface
{
    public function __construct(
        private RankWindowFunction $rank,
    ) {
    }

    public function getRank()
    {
        return $this->rank;
    }
}
