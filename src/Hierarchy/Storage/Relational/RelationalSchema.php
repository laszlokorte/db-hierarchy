<?php

namespace App\Hierarchy\Storage\Relational;

use App\Hierarchy\Storage\Relational\Algebra\CreateTable;
use App\Hierarchy\Storage\Relational\Algebra\CreateView;
use App\Hierarchy\Storage\Relational\Algebra\Select;
use App\Hierarchy\Storage\Relational\Algebra\Value\Constant;
use App\Hierarchy\Storage\Relational\Algebra\TableColumn;
use App\Hierarchy\Storage\Relational\Algebra\TableReference;
use App\Hierarchy\Storage\Relational\Algebra\Value\ColumnReference;
use App\Hierarchy\Storage\Relational\Algebra\Value\Existence;
use App\Hierarchy\Storage\Relational\Algebra\Identifier;
use App\Hierarchy\Storage\Relational\Algebra\ColumnName;
use App\Hierarchy\Storage\Relational\Algebra\ForeignKey;
use App\Hierarchy\Storage\Relational\Algebra\Projection;
use App\Hierarchy\Storage\Relational\Algebra\Join;

use App\Hierarchy\Storage\Relational\Algebra\Value\BinaryOperation;
use App\Hierarchy\Storage\Relational\Algebra\Value\UnaryOperation;
use App\Hierarchy\Storage\Relational\Algebra\Value\AssociativeOperation;
use App\Hierarchy\Storage\Relational\Algebra\Value\Tuple;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Logic\Disjunction;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Logic\Conjunction;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Logic\Negation;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Comparison\Equal;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Comparison\NotEqual;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Comparison\GreaterThan;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Numeric\Addition;

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
			$this->fieldColumnToname($columnDefinition),
			$this->storageCodingToSqlType($columnDefinition->getStorageCoding()),
			$columnDefinition->isNullable(),
			$columnDefinition->getDefault()
		);
	}

	private function fieldColumnToName(ColumnDefinition $columnDefinition) {
		return new Identifier($columnDefinition->getName());
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

		$parentColumn = $this->schemaDef->getKeyReflexivityParentColumn($keyId);
		$childColumn = $this->schemaDef->getKeyReflexivityChildColumn($keyId);

		$table = new TableReference(new Identifier($this->closureTableName($keyId)), new Identifier('t'));
		$tableA = new TableReference(new Identifier($this->closureTableName($keyId)), new Identifier('a'));
		$tableB = new TableReference(new Identifier($this->closureTableName($keyId)), new Identifier('b'));
		$tableR = new TableReference(new Identifier($this->closureTableName($keyId)), new Identifier('r'));

		$idRef = new ColumnReference($table, new Identifier('id'));
		$parentRef = new ColumnReference($table, $this->fieldColumnToName($parentColumn));
		$childRef = new ColumnReference($table, $this->fieldColumnToName($childColumn));
		$depthRef = new ColumnReference($table, new Identifier('depth'));


		$idRefA = new ColumnReference($tableA, new Identifier('id'));
		$parentRefA = new ColumnReference($tableA, $this->fieldColumnToName($parentColumn));
		$childRefA = new ColumnReference($tableA, $this->fieldColumnToName($childColumn));
		$depthRefA = new ColumnReference($tableA, new Identifier('depth'));


		$idRefB = new ColumnReference($tableB, new Identifier('id'));
		$parentRefB = new ColumnReference($tableB, $this->fieldColumnToName($parentColumn));
		$childRefB = new ColumnReference($tableB, $this->fieldColumnToName($childColumn));
		$depthRefB = new ColumnReference($tableB, new Identifier('depth'));

		$idRefR = new ColumnReference($tableR, new Identifier('id'));
		$parentRefR = new ColumnReference($tableR, $this->fieldColumnToName($parentColumn));
		$childRefR = new ColumnReference($tableR, $this->fieldColumnToName($childColumn));

		$projections = [
			new Projection($idRef),
			new Projection($parentRef),
			new Projection($childRef),
			new Projection($depthRef),
		];

		if($this->schemaDef->isKeyScoped($keyId)) {
			$scopeColumn = $this->schemaDef->getKeyScopeColumn($keyId);
			$projections[] = new Projection(new ColumnReference($table, $this->fieldColumnToName($scopeColumn)));
		}

		$conditionA = new BinaryOperation(
			new Conjunction(), 
			new BinaryOperation(
				new Equal(), 
				$depthRef,
				new Constant(0)
			),
			new BinaryOperation(
				new NotEqual(), 
				$parentRef,
				$childRef
			)
		);

		$conditionB = new BinaryOperation(
			new Conjunction(), 
			new BinaryOperation(
				new NotEqual(), 
				$depthRef,
				new Constant(0)
			),
			new BinaryOperation(
				new Equal(true), 
				$parentRef,
				$childRef
			)
		);

		$conditionC = new BinaryOperation(
			new Conjunction(), 
			new BinaryOperation(
				new GreaterThan(), 
				$depthRef,
				new Constant(1)
			),
			new UnaryOperation(
				new Negation(), 
				new Existence(
					new Select(
						[$idRefA], 
						[$tableA], 
						[new Join($tableB, 
							new BinaryOperation(new Equal(), $childRefA,$parentRefB))
						],
						new AssociativeOperation(
							new Conjunction(),
							[
								new BinaryOperation(
									new Equal(), 
									new BinaryOperation(new Addition(), $depthRefA, $depthRefB),
									$depthRef
								),
								new BinaryOperation(new NotEqual(), $idRefA,$idRef),
								new BinaryOperation(new NotEqual(), $idRefB,$idRef),
								new BinaryOperation(
									new Equal(true), 
									new Tuple([$parentRef, $childRef]), 
									new Tuple([$parentRefA, $childRefA])
								)
							]
						)
					)
				)
			)
		);

	
		$conditionD = new BinaryOperation(
			new Conjunction(), 
			new UnaryOperation(
				new Negation(), 
				new BinaryOperation(
					new Equal(), 
					$parentRef,
					$childRef
				)
			),
			new Existence(new Select([$idRefR], [$tableR], [], 
				new BinaryOperation(
					new Equal(), 
					new Tuple([$childRefR, $parentRefR]), 
					new Tuple([$parentRef, $childRef])
				)
			))
		);

		$condition = new AssociativeOperation(
			new Disjunction(),
			[$conditionA, $conditionB, $conditionC, $conditionD]
		);

		$select = new Select($projections, [
			$table
		], [], $condition);

		/*
		implode(', '.PHP_EOL, array_map(fn($c) => str_replace('{c}', $c->getName(), 't.{c} AS {c}'), $closureTable->getColumns()))
		SELECT
				{{closureColumns}}
			FROM
				{{closure_table}} t
			WHERE (t.{{closure_depth}} = 0 AND t.{{closure_child}} <> t.{{closure_parent}}) 
			OR (t.{{closure_depth}} <> 0 AND t.{{closure_child}} IS t.{{closure_parent}}) 
			OR (t.{{closure_depth}} > 1 AND NOT EXISTS (
					SELECT a.{{closure_id}}
					FROM {{closure_table}} a
					INNER JOIN {{closure_table}} b ON a.{{closure_child}} = b.{{closure_parent}}
					WHERE (a.{{closure_depth}} + b.{{closure_depth}}) = t.{{closure_depth}}
						AND a.{{closure_id}} <> t.{{closure_id}}
						AND b.{{closure_id}} <> t.{{closure_id}}
						AND (t.{{closure_parent}}, t.{{closure_child}})
						IS (a.{{closure_parent}}, b.{{closure_child}})
						AND (a.{{closure_scope}}, b.{{closure_scope}}) IS (t.{{closure_scope}}, t.{{closure_scope}}) ---SCOPEONLY
			)) 
			OR (t.{{closure_child}} <> t.{{closure_parent}} AND EXISTS (
					SELECT r.{{closure_id}}
					FROM {{closure_table}} r
					WHERE (r.{{closure_child}}, r.{{closure_parent}}) = (t.{{closure_parent}}, t.{{closure_child}})
				)
			)
		*/

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