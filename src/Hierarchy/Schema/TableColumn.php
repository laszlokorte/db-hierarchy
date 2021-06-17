<?php

namespace App\Hierarchy\Schema;

class TableColumn {
	public function __construct(
		private string $name, 
		private string $sqlType,
		private ?bool $nullable = false,
		private ?string $default = null
	) {
	}

	public function getName() {
		return $this->name;
	}

	public function getSqlType() {
		return $this->sqlType;
	}

	public function deriveSameWithName($columnName) {
		return new self($columnName, $this->sqlType, $this->nullable, $this->default);
	}
}