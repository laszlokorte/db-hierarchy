<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

enum JoinDirection
{
    case LEFT;
    case RIGHT;
    case OUTER;
    case INNER;
}
