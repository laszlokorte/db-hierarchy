<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;

class Update {
	public function __construct(
		private TableReference $name,
		private array $setters,
		private ValueInterface $condition,
		private ?Select $selection = NULL 
	) {

	}
}