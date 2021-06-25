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
	public const TIME = 'TIME';
	public const DATETIME = 'DATETIME';
	public const DATE = 'DATE';
	public const BINARY = 'BINARY';
	public const ENUM = 'ENUM';

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