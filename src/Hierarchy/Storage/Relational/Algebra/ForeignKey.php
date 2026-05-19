<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

class ForeignKey {
	public const CASCADE = 'CASCADE';
	public const RESTRICT = 'RESTRICT';
	public const SET_NULL = 'SET NULL';

	public function __construct(
		private array $ownColumns,
		private Identifier $foreignTable,
		private array $targetColumns,
		private string $onDelete = 'CASCADE'
	) {

	}

	public function getOwnColumns() {
		return $this->ownColumns;
	}

	public function getForeignTable() {
		return $this->foreignTable;
	}

	public function getTargetColumns() {
		return $this->targetColumns;
	}

	public function getOnDelete() {
		return $this->onDelete;
	}
}