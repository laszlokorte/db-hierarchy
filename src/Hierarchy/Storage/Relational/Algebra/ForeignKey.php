<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

class ForeignKey {
	public function __construct(
		private array $ownColumns,
		private Identifier $foreignTable,
		private array $targetColumns
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
}