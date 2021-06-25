<?php

namespace App\Hierarchy\Schema\Definition;

class ColumnDefinition {
	public function __construct(
		private string $name, 
		private StorageCoding|ReferenceCoding $coding,
		private ?bool $nullable = false,
		private ?string $default = null
	) {
	}

	public function getName() {
		return $this->name;
	}

	public function getCoding() {
		return $this->coding;
	}

	public function isReference() {
		return $this->coding instanceof ReferenceCoding;
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
		return new self($columnName, $this->coding, $this->nullable, $this->default);
	}

	public function isReferencing($keyId) {
		return $this->isReference() && $this->coding->isReferencing($keyId);
	}
}