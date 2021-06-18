<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

class TableColumn {
	public function __construct(
		private string $name,
		private string $type,
		private string $nullable,
		private string $default
	) {

	}
}