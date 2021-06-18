<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Operator\Logic;

use App\Hierarchy\Storage\Relational\Algebra\Operator\BinaryInterface;

class Equal implements BinaryInterface {
	public function __construct(private bool $allowNull) {
		
	}
}