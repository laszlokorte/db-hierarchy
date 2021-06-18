<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

class TableColumn {
	public function __construct(
		private Identifier $name,
		private string $type,
		private bool $nullable = true,
		private ?string $default = null
	) {

	}

	public function getName() {
		return $this->name;
	}
}