<?php

namespace App\Hierarchy\Storage\Relational\Adapter;

use App\Hierarchy\Storage\Relational\Algebra;
use App\Hierarchy\Storage\Relational\Algebra\Identifier;
use App\Hierarchy\Storage\Relational\Algebra\Select;
use App\Hierarchy\Storage\Relational\Algebra\Insert;
use App\Hierarchy\Storage\Relational\Algebra\Update;
use App\Hierarchy\Storage\Relational\Algebra\Delete;
use App\Hierarchy\Storage\Relational\Algebra\CreateView;
use App\Hierarchy\Storage\Relational\Algebra\CreateTable;
use App\Hierarchy\Storage\Relational\Algebra\TableColumn;
use App\Hierarchy\Storage\Relational\Algebra\ForeignKey;

class Sqlite implements AdapterInterface {
	private const INDENT = "\t";

	public function selectToString(Select $select) {
		return "SELECT %s";
	}

	public function insertToString(Insert $insert) {
		return "INSERT INTO %s";
	}

	public function updateToString(Update $update) {
		return "UPDATE %s";
	}

	public function deleteToString(Delete $delete) {
		return "DELETE FROM %s";
	}

	public function createViewToString(CreateView $createView) {
		return "CREATE VIEW IF NOT EXISTS " . $this->escapeIdentifier($createView->getName()) .
		" AS " . PHP_EOL . $this->selectToString($createView->getQuery()) . ";";
	}

	public function createTableToString(CreateTable $createTable) {
		$indent = "\t";
		$query = "CREATE TABLE IF NOT EXISTS ";
		$query .= $this->escapeIdentifier($createTable->getName()) . "(". PHP_EOL;
			foreach($createTable->getColumns() AS $column) {
				$query .= self::INDENT . $this->tableColumnToString($column) . ',' . PHP_EOL;
			}
			$query .= self::INDENT . $this->tablePrimaryColumnToString($createTable->getPrimaryKey());
			foreach($createTable->getUniques() AS $unique) {
				$query .= ',' . PHP_EOL . self::INDENT . $this->uniqueIndexToString($unique);
			}
			foreach($createTable->getForeignKeys() AS $fk) {
				$query .= ',' . PHP_EOL . self::INDENT . $this->foreignKeyToString($fk);
			}

		$query .= PHP_EOL . ")" . ";";

		return $query;
	}

	private function tableColumnToString(TableColumn $column) {
		return $this->escapeIdentifier($column->getName());
	}

	private function tablePrimaryColumnToString(Identifier $columnName) {
		return $this->escapeIdentifier($columnName);
	}

	private function uniqueIndexToString(array $columnNames) {
		return 'UNIQUE('. implode(', ', array_map(
			fn($name) => $this->escapeIdentifier($name),
			$columnNames
		)) .')';
	}

	private function foreignKeyToString(ForeignKey $fk) {
		return 'FOREIGN KEY('. implode(', ', array_map(
			fn($name) => $this->escapeIdentifier($name),
			$fk->getOwnColumns()
		)) .')' . 'REFERENCES ' . $this->escapeIdentifier($fk->getForeignTable()) . 
		'(' . implode(', ', array_map(
			fn($name) => $this->escapeIdentifier($name),
			$fk->getTargetColumns()
		)) . ') ' . PHP_EOL . self::INDENT . 'ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED';
	}


	public function dropViewToString(CreateView $createView) {
		return "DROP VIEW IF EXISTS " . $this->escapeIdentifier($createView->getName()) . ";";
	}

	public function dropTableToString(CreateTable $createTable) {
		return "DROP TABLE IF EXISTS " . $this->escapeIdentifier($createTable->getName()) . ";";
	}

	private function escapeIdentifier(Identifier $identifier) {
		return sprintf('"%s"', str_replace(['/','"'], '', $identifier->getString()));
	}
}