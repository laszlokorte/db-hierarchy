<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

use App\Hierarchy\Storage\Relational\Algebra\Value\Constant;

class TableColumn {
	public function __construct(
		private Identifier $name,
		private string $type,
		private bool $nullable = true,
		private ?Constant $default = null,
		private bool $serial = false
	) {
	}

	public function getName() {
		return $this->name;
	}

	public function getType() {
		return $this->type;
	}

	public function isNullable() {
		return $this->nullable;
	}

	public function getDefault() {
		return $this->default;
	}

	public function hasDefault() {
		return $this->default !== NULL;
	}

	public function isSerial() {
		return $this->serial;
	}
}