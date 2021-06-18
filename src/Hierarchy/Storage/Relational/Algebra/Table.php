<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

class Table {
	public function __construct(
		private string $name,
		private string $columns,
		private string $uniques,
		private string $foreignKeys
	) {

	}
}