<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Operator\Logic;

use App\Hierarchy\Storage\Relational\Algebra\Operator\AssociativeInterface;
use App\Hierarchy\Storage\Relational\Algebra\Operator\BinaryInterface;

class Conjunction implements BinaryInterface, AssociativeInterface
{
    public function __construct()
    {
    }
}
