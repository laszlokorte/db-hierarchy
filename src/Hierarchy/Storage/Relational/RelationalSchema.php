<?php

namespace App\Hierarchy\Storage\Relational;

use App\Hierarchy\Storage\Relational\Algebra\CreateTable;
use App\Hierarchy\Storage\Relational\Algebra\CreateView;
use App\Hierarchy\Storage\Relational\Algebra\Select;
use App\Hierarchy\Storage\Relational\Algebra\Value\Constant;
use App\Hierarchy\Storage\Relational\Algebra\TableColumn;
use App\Hierarchy\Storage\Relational\Algebra\TableReference;
use App\Hierarchy\Storage\Relational\Algebra\Identifier;
use App\Hierarchy\Storage\Relational\Algebra\ColumnName;
use App\Hierarchy\Storage\Relational\Algebra\ForeignKey;

use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Schema\Definition\ColumnDefinition;

class RelationalSchema {
	public function __construct(private SchemaDefinition $schemaDef) {
	}

	public function getTablesFor(string $keyId) {
		$tables = [
			$this->buildNodeTable($keyId),
		];

		if($this->schemaDef->isKeyReflexive($keyId)) {
			$tables[] = $this->buildClosureTable($keyId);
		}

		return $tables;
	}
	public function getViewsFor(string $keyId) {
		$views = [
			$this->buildHierarchyView($keyId),
		];

		if($this->schemaDef->isKeyReflexive($keyId)) {
			$views[] = $this->buildClosureInvalidsView($keyId);
			$views[] = $this->buildClosureMissingsView($keyId);
		}

		if($this->schemaDef->isKeyOrdered($keyId)) {
			$views[] = $this->buildNormalizedOrderView($keyId);
		}

		return $views;
	}

	public function getAllTables() {
		$tables = [];
		foreach ($this->schemaDef->getAllKeyIdsTopological() as $keyId) {
			$tables = array_merge($tables, $this->getTablesFor($keyId));
		}

		return $tables;
	}

	public function getAllViews() {
		$views = [];
		foreach ($this->schemaDef->getAllKeyIdsTopological() as $keyId) {
			$views = array_merge($views, $this->getViewsFor($keyId));
		}

		return $views;
	}

	private function buildNodeTable($keyId) {
		$tableName = new Identifier($this->nodeTableName($keyId));
		$pkColumn = $this->schemaDef->getKeyIdentityColumn($keyId);
		$pkColumnName = new Identifier($pkColumn->getName());

		$columns = [
			$this->fieldColumnToTableColumn($pkColumn),
		];

		if($this->schemaDef->isKeyOrdered($keyId)) {
			$columns[] = $this->fieldColumnToTableColumn($this->schemaDef->getKeyOrderColumn($keyId));
		}

		if($this->schemaDef->isKeyScoped($keyId)) {
			$scopeColumn = $this->schemaDef->getKeyScopeColumn($keyId);
			$columns[] = $this->fieldColumnToTableColumn($scopeColumn);

			if($this->schemaDef->isKeyScopedUnique($keyId)) {
				if(!$this->schemaDef->isKeyReflexive($keyId)) {
					$uniques[] = [new Identifier($scopeColumn->getName())];
				}
			}
		}

		foreach ($this->fieldsColumns($keyId) as $fieldColumn) {
			$columns[] = $this->fieldColumnToTableColumn($fieldColumn);
		}

		$uniques = [];

		

		$uniqueFieldsIds = array_filter(
			$this->schemaDef->getKeyFieldIds($keyId), 
			fn($fieldId) => $this->schemaDef->isKeyFieldUnique($keyId, $fieldId)
		);

		foreach ($uniqueFieldsIds as $ufid) {
			$uniques[] = $this->getFieldColumns($ufid);
		}

		$foreignKeys = [];

		if($this->schemaDef->isKeyScoped($keyId)) {
			$scopeKeyId = $this->schemaDef->getKeyScopeId($keyId);

			$ownColumnName = $this->schemaDef->getKeyScopeColumn($keyId)->getName();
			$targetColumnName = $this->schemaDef->getKeyIdentityColumn($scopeKeyId)->getName();

			$targetTableName = $this->schemaDef->getKeyTableName($scopeKeyId);

			$foreignKeys[] = new ForeignKey(
				[new Identifier($ownColumnName)], 
				new Identifier($targetTableName), 
				[new Identifier($targetColumnName)]
			);
		}

		return new CreateTable($tableName, $pkColumnName, $columns, $uniques, $foreignKeys);
	}



	private function fieldsColumns($keyId) {
		return array_merge([], ...array_map(fn($fieldId) => $this->getFieldColumns($keyId, $fieldId), $this->schemaDef->getKeyFieldIds($keyId)));
	}

	private function getFieldColumns($keyId, $fieldId) {
		$fieldType = $this->schemaDef->getKeyFieldType($keyId, $fieldId);
		$options = $this->schemaDef->getKeyFieldOptions($keyId, $fieldId);

		return $fieldType->getColumns($fieldId, $options);
	}

	private function fieldColumnToTableColumn(ColumnDefinition $columnDefinition) {
		return new TableColumn(
			new Identifier($columnDefinition->getName()),
			$this->storageCodingToSqlType($columnDefinition->getStorageCoding()),
			$columnDefinition->isNullable(),
			$columnDefinition->getDefault()
		);
	}

	private function storageCodingToSqlType($storageCoding) {
		return $storageCoding;
	}

	private function nodeTableName($keyId) {
		return $this->schemaDef->getKeyTableName($keyId);
	}

	private function closureTableName($keyId) {
		return $this->schemaDef->getKeyReflexivityTableName($keyId);
	}

	private function buildClosureTable($keyId) {
		$scopeColumnNames = [];
		$pkColumnName = new Identifier('id');

		$columns = [
			new TableColumn($pkColumnName, 'INTEGER', false, null),
		];

		$targetTableName = $this->schemaDef->getKeyTableName($keyId);
		$targetColumn = $this->schemaDef->getKeyIdentityColumn($keyId);
		$parentColumn = $this->schemaDef->getKeyReflexivityParentColumn($keyId);
		$childColumn = $this->schemaDef->getKeyReflexivityChildColumn($keyId);

		if($this->schemaDef->isKeyScoped($keyId)) {
			$scopeColumn = $this->schemaDef->getKeyScopeColumn($keyId);
			$scopeColumnNames[] = new Identifier($scopeColumn->getName());

			$columns[] = $this->fieldColumnToTableColumn($scopeColumn);
		}

		$columns[] = $this->fieldColumnToTableColumn($parentColumn);
		$columns[] = $this->fieldColumnToTableColumn($childColumn);
		$depthColumnName = new Identifier('depth');
		$columns[] = new TableColumn($depthColumnName, 'INTEGER', false, null);


		$targetColumnName = new Identifier($targetColumn->getName());
		$parentColumnName = new Identifier($parentColumn->getName());
		$childColumnName = new Identifier($childColumn->getName());

		$uniques = [
			[$childColumnName, $parentColumnName],
			[$childColumnName, $depthColumnName],
		];

		if($this->schemaDef->isKeyScoped($keyId) && $this->schemaDef->isKeyScopedUnique($keyId)) {
			$uniques[] = array_merge($scopeColumnNames, [$depthColumnName]);
		}

		$foreignKeys = [
			new ForeignKey(
				array_merge($scopeColumnNames, [$childColumnName]), 
				new Identifier($targetTableName), 
				array_merge($scopeColumnNames, [$targetColumnName])
			),
			new ForeignKey(
				array_merge($scopeColumnNames, [$parentColumnName]), 
				new Identifier($targetTableName), 
				array_merge($scopeColumnNames, [$targetColumnName])
			)
		];
		
		return new CreateTable(new Identifier($this->closureTableName($keyId)), $pkColumnName, $columns, $uniques, $foreignKeys);
	}

	private function buildHierarchyView($keyId) {
		$values = [];
		$values[] = new Constant(1);
		$select = new Select($values);
		return new CreateView(new Identifier($this->hierarchyViewName($keyId)), $select);
	}

	public function hierarchyViewName($keyId) {
		return sprintf('_%s_hierarchy', $this->schemaDef->getKeyTableName($keyId));
	}

	private function buildClosureInvalidsView($keyId) {
		$values = [];
		$values[] = new Constant(1);
		$select = new Select($values);

		return new CreateView(new Identifier($this->closureInvalidViewName($keyId)), $select);
	}

	public function closureInvalidViewName($keyId) {
		return sprintf('_%s_invalid', $this->schemaDef->getKeyReflexivityTableName($keyId));
	}

	private function buildClosureMissingsView($keyId) {
		$values = [];
		$values[] = new Constant(1);
		$select = new Select($values);
		
		return new CreateView(new Identifier($this->closureMissingViewName($keyId)), $select);
	}

	public function closureMissingViewName($keyId) {
		return sprintf('_%s_missing', $this->schemaDef->getKeyReflexivityTableName($keyId));
	}

	private function buildNormalizedOrderView($keyId) {
		$values = [];
		$values[] = new Constant(1);
		$select = new Select($values);
		
		return new CreateView(new Identifier($this->normalizedOrderViewName($keyId)), $select);
	}

	public function normalizedOrderViewName($keyId) {
		return sprintf('_%s_hierarchy', $this->schemaDef->getKeyTableName($keyId));
	}

}