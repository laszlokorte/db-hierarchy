<?php

namespace App\Hierarchy\Schema\Definition;

use App\Hierarchy\Schema\Definition\ColumnDefinition;

class StorageDefinition {
	public function __construct(
		private string $tableName, 
		private string $pkColumn = 'id',
	) {
	}

	public function getTableName() {
		return $this->tableName;
	}

	public function getIdColumnName() {
		return $this->pkColumn;
	}

	public function getIdColumn() {
		return new ColumnDefinition($this->pkColumn, 'INTEGER', false, null);
	}
}