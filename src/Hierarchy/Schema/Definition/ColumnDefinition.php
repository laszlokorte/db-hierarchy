<?php

namespace App\Hierarchy\Schema\Definition;

class ColumnDefinition {
	public function __construct(
		private string $name, 
		private string $storageCoding,
		private ?bool $nullable = false,
		private ?string $default = null
	) {
	}

	public function getName() {
		return $this->name;
	}

	public function getStorageCoding() {
		return $this->storageCoding;
	}

	public function isNullable() {
		return $this->nullable;
	}

	public function hasDefault() {
		return $this->default !== null;
	}

	public function getDefault() {
		return $this->default;
	}

	public function deriveSameWithName($columnName) {
		return new self($columnName, $this->storageCoding, $this->nullable, $this->default);
	}
}