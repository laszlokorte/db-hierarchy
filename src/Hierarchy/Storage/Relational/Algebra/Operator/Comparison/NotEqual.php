<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Operator\Comparison;

use App\Hierarchy\Storage\Relational\Algebra\Operator\BinaryInterface;

class NotEqual implements BinaryInterface {
	public function __construct(private bool $allowNull = false) {
		
	}

	public function allowNull() {
		return $this->allowNull;
	}
}