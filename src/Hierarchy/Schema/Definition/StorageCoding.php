<?php

namespace App\Hierarchy\Schema\Definition;

class StorageCoding {
	public const REFERENCE = 'REFERENCE';
	public const STRING = 'STRING';
	public const TEXT = 'TEXT';
	public const INTEGER = 'INTEGER';
	public const FLOAT = 'FLOAT';
	public const DECIMAL = 'DECIMAL';
	public const BOOL = 'BOOL';

	public function __construct(
		private string $type, 
		private ?string $parameter = NULL
	) {
	}

	public function getType() {
		return $this->type;
	}

	public function getParameter() {
		return $this->parameter;
	}

	public function isReference() {
		return self::REFERENCE === $this->type;
	}
}