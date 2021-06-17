<?php

namespace App\Hierarchy\Schema\Definition;

use App\Hierarchy\Schema\TableColumn;

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
		return new TableColumn($this->pkColumn, 'UNSIGNED INTEGER', false, null);
	}
}