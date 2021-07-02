<?php

namespace App\Hierarchy\Schema\Definition;

use App\Hierarchy\Schema\Definition\ColumnDefinition;

class StorageDefinition {
	public function __construct(
		private string $tableName, 
		private string $pkColumn = 'id',
		private string $pkType = 'uuid'
	) {
	}

	public function getTableName() {
		return $this->tableName;
	}

	public function getIdColumnName() {
		return $this->pkColumn;
	}

	public function getIdColumnType() {
		return $this->pkType;
	}

	public function getIdColumn() {
		switch($this->pkType) {
			case 'serial':
				return new ColumnDefinition($this->pkColumn, new StorageCoding(StorageCoding::SERIAL), false, null);
			case 'uuid':
				return new ColumnDefinition($this->pkColumn, new StorageCoding(StorageCoding::UUID), false, null);
			case 'manual':
				return new ColumnDefinition($this->pkColumn, new StorageCoding(StorageCoding::INTEGER), false, null);
		}
	}
}