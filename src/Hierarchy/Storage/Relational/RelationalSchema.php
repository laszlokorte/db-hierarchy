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
use App\Hierarchy\Storage\Relational\Algebra\Order;

use App\Hierarchy\Storage\Relational\Algebra\Value\BinaryOperation;
use App\Hierarchy\Storage\Relational\Algebra\Value\UnaryOperation;
use App\Hierarchy\Storage\Relational\Algebra\Value\AssociativeOperation;
use App\Hierarchy\Storage\Relational\Algebra\Value\Tuple;
use App\Hierarchy\Storage\Relational\Algebra\Value\Windowing;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Logic\Disjunction;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Logic\Conjunction;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Logic\Negation;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Comparison\Equal;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Comparison\NotEqual;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Comparison\GreaterThan;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Numeric\Addition;
use App\Hierarchy\Storage\Relational\Algebra\Windowing\RankWindow;
use App\Hierarchy\Storage\Relational\Algebra\Windowing\Rank\RowNumber;


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
		$tableName = $this->nodeTableName($keyId);
		$pkColumn = $this->schemaDef->getKeyIdentityColumn($keyId);
		$pkColumnName = $this->nodeTablePKName($keyId);

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
			$ownColumnName = $this->nodeOwnScopeColumnName($keyId);
			$targetColumnName = $this->scopeTablePKName($keyId);

			$targetTableName = $this->nodeTableName($keyId);

			$foreignKeys[] = new ForeignKey(
				[$ownColumnName], 
				$targetTableName, 
				[$targetColumnName]
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
			new Constant($columnDefinition->getDefault())
		);
	}

	private function fieldColumnToName(ColumnDefinition $columnDefinition) {
		return new Identifier($columnDefinition->getName());
	}

	private function storageCodingToSqlType($storageCoding) {
		return $storageCoding;
	}

	private function nodeTableName($keyId) {
		return new Identifier($this->schemaDef->getKeyTableName($keyId));
	}

	private function nodeTablePKName($keyId) {
		$pkColumn = $this->schemaDef->getKeyIdentityColumn($keyId);
		return new Identifier($pkColumn->getName());
	}

	private function scopeTablename($keyId) {
		return $this->nodeTableName($this->schemaDef->getKeyScopeId($keyId));
	}

	private function scopeTablePKName($keyId) {
		return $this->nodeTablePKName($this->schemaDef->getKeyScopeId($keyId));
	}

	private function nodeOwnScopeColumnName($keyId) {
		return $this->fieldColumnToName($this->schemaDef->getKeyScopeColumn($keyId));
	}

	private function closureTableName($keyId) {
		return new Identifier($this->schemaDef->getKeyReflexivityTableName($keyId));
	}

	private function buildClosureTable($keyId) {
		$scopeColumnNames = [];
		$pkColumnName = new Identifier('id');

		$columns = [
			new TableColumn($pkColumnName, 'INTEGER', false, null),
		];

		$targetTableName = $this->nodeTableName($keyId);
		$parentColumn = $this->schemaDef->getKeyReflexivityParentColumn($keyId);
		$childColumn = $this->schemaDef->getKeyReflexivityChildColumn($keyId);

		if($this->schemaDef->isKeyScoped($keyId)) {
			$scopeColumn = $this->schemaDef->getKeyScopeColumn($keyId);
			$scopeColumnNames[] = new Identifier($scopeColumn->getName());

			$columns[] = $this->fieldColumnToTableColumn($scopeColumn);
		}

		$columns[] = $this->fieldColumnToTableColumn($parentColumn);
		$columns[] = $this->fieldColumnToTableColumn($childColumn);
		$depthId = new Identifier('depth');
		$columns[] = new TableColumn($depthId, 'INTEGER', false, null);


		$targetColumnName = $this->nodeTablePKName($keyId);
		$parentColumnName = new Identifier($parentColumn->getName());
		$childColumnName = new Identifier($childColumn->getName());

		$uniques = [
			[$childColumnName, $parentColumnName],
			[$childColumnName, $depthId],
		];

		if($this->schemaDef->isKeyScoped($keyId) && $this->schemaDef->isKeyScopedUnique($keyId)) {
			$uniques[] = array_merge($scopeColumnNames, [$depthId]);
		}

		$foreignKeys = [
			new ForeignKey(
				array_merge($scopeColumnNames, [$childColumnName]), 
				$targetTableName, 
				array_merge($scopeColumnNames, [$targetColumnName])
			),
			new ForeignKey(
				array_merge($scopeColumnNames, [$parentColumnName]), 
				$targetTableName, 
				array_merge($scopeColumnNames, [$targetColumnName])
			)
		];
		
		return new CreateTable($this->closureTableName($keyId), $pkColumnName, $columns, $uniques, $foreignKeys);
	}

	private function buildHierarchyView($keyId) {
		$table = new TableReference($this->nodeTableName($keyId), new Identifier('t'));
		$pkId = $this->nodeTablePKName($keyId);
		$idRef = new ColumnReference($table, $pkId);

		$joins = [];
		$orders = [];

		if($this->schemaDef->isKeyScoped($keyId)) {
			$scopePkId = $this->scopeTablePKName($keyId);

			$tableScope = new TableReference($this->scopeTableName($keyId), new Identifier('s'));
			$idRefScope = new ColumnReference($tableScope, $scopePkId);
			$scopeRef = new ColumnReference($table, $this->nodeOwnScopeColumnName($keyId));
			$scopeProjection = new Projection($idRef, new Identifier('_scope'));

			$joins[] = new Join($tableScope, 
				new BinaryOperation(
					new Equal(),
					$scopeRef,
					$idRefScope
				)
			,'INNER');
		} else {
			$scopeProjection = new Projection(new Constant(null), new Identifier('_scope'));
		}

		if($this->schemaDef->isKeyReflexive($keyId)) {

			$parentColumn = $this->schemaDef->getKeyReflexivityParentColumn($keyId);
			$childColumn = $this->schemaDef->getKeyReflexivityChildColumn($keyId);

			$parentId = $this->fieldColumnToName($parentColumn);
			$childId = $this->fieldColumnToName($childColumn);
			$depthId = new Identifier('depth');

			$tableParent = new TableReference($this->nodeTableName($keyId), new Identifier('p'));
			$tableReflexive = new TableReference($this->closureTableName($keyId), new Identifier('r'));
			$tableClosure = new TableReference($this->closureTableName($keyId), new Identifier('c'));
			
			$idRefParent = new ColumnReference($tableParent, new Identifier('id'));
			$parentRefClosure = new ColumnReference($tableClosure, $parentId);
			$childRefClosure = new ColumnReference($tableClosure, $childId);
			$depthRefClosure = new ColumnReference($tableClosure, $depthId);

			$parentRefRelexive = new ColumnReference($tableReflexive, $parentId);
			$childRefRelexive = new ColumnReference($tableReflexive, $childId);
			$depthRefRelexive = new ColumnReference($tableReflexive, $depthId);


			$parentProjection = new Projection($idRef, new Identifier('_parent'));
	
			$joins[] = new Join($tableReflexive, 
				new BinaryOperation(
					new Equal(),
					new Tuple([$depthRefRelexive, $childRefRelexive, $parentRefRelexive]), 
					new Tuple([new Constant(0), $idRef, $idRef])
				)
			,'INNER');

			$joins[] = new Join($tableClosure, 
				new BinaryOperation(
					new Equal(),
					new Tuple([$childRefClosure, $depthRefClosure]), 
					new Tuple([$idRef, new Constant(1)])
				)
			,'LEFT');

			$joins[] = new Join($tableParent, 
				new BinaryOperation(
					new Equal(),
					$idRefParent, 
					$parentRefClosure
				)
			,'LEFT');

			$orders[] = new Order($idRefParent, true);
		} else {
			$parentProjection = new Projection(new Constant(null), new Identifier('_parent'));
		}

		if($this->schemaDef->isKeyOrdered($keyId)) {
			$orderColumn = $this->schemaDef->getKeyOrderColumn($keyId);
			$orderRef = new ColumnReference($table, $this->fieldColumnToName($orderColumn));

			$orderProjection = new Projection($orderRef, new Identifier('_order'));

			$orders[] = new Order($orderRef, $this->schemaDef->getKeyOrderDirection($keyId) === 'ASC');
		} else {
			$orderProjection = new Projection(new Constant(null), new Identifier('_order'));
		}

		$projections = [
			$scopeProjection,
			$parentProjection,
			$orderProjection,
			new Projection($idRef, new Identifier('_id')),
		];

		$orders[] = new Order($idRef, true);

		$select = new Select($projections, [$table], $joins, NULL, $orders);

		/*
			SELECT
				self.{{scope_source}} AS _scope, ---SCOPEONLY NULL AS _scope,
				parent.{{self_id}} AS _parent, ---REFLEXONLY NULL AS _parent,
				self.{{order_column}} AS _order, ---ORDERONLY NULL AS _order,
				self.{{self_id}} AS _id
			FROM
				{{self_table}} self
				INNER JOIN {{scope_table}} scope ---SCOPEONLY
				ON scope.{{scope_target}} = self.{{scope_source}} ---SCOPEONLY
				INNER JOIN {{closure_table}} reflexive ---REFLEXONLY
				ON reflexive.{{closure_depth}} = 0 ---REFLEXONLY
				AND reflexive.{{closure_child}} = self.{{self_id}} ---REFLEXONLY
				AND reflexive.{{closure_parent}} = self.{{self_id}} ---REFLEXONLY
				LEFT JOIN {{closure_table}} closure ---REFLEXONLY
				ON closure.{{closure_child}} = self.{{self_id}} AND closure.{{closure_depth}} = 1 ---REFLEXONLY
				LEFT JOIN {{self_table}} parent ---REFLEXONLY
				ON parent.{{self_id}} = closure.{{closure_parent}} ---REFLEXONLY
			ORDER BY
			    parent.{{self_id}} ASC,  ---REFLEXONLY
			    self.{{order_column}} {{order_direction}}, ---ORDERONLY
				self.{{self_id}} ASC
		*/
		return new CreateView($this->hierarchyViewName($keyId), $select);
	}

	public function hierarchyViewName($keyId) {
		return new Identifier(sprintf('_%s_hierarchy', $this->schemaDef->getKeyTableName($keyId)));
	}

	private function buildClosureInvalidsView($keyId) {
		$values = [];
		$values[] = new Projection(new Constant(1));
		$select = new Select($values);

		$parentColumn = $this->schemaDef->getKeyReflexivityParentColumn($keyId);
		$childColumn = $this->schemaDef->getKeyReflexivityChildColumn($keyId);

		$parentId = $this->fieldColumnToName($parentColumn);
		$childId = $this->fieldColumnToName($childColumn);

		$table = new TableReference($this->closureTableName($keyId), new Identifier('t'));
		$tableA = new TableReference($this->closureTableName($keyId), new Identifier('a'));
		$tableB = new TableReference($this->closureTableName($keyId), new Identifier('b'));
		$tableR = new TableReference($this->closureTableName($keyId), new Identifier('r'));

		$idColumnName = new Identifier('id');
		$depthId = new Identifier('depth');
		$idRef = new ColumnReference($table, $idColumnName);
		$parentRef = new ColumnReference($table, $parentId);
		$childRef = new ColumnReference($table, $childId);
		$depthRef = new ColumnReference($table, $depthId);


		$idRefA = new ColumnReference($tableA, $idColumnName);
		$parentRefA = new ColumnReference($tableA, $parentId);
		$childRefA = new ColumnReference($tableA, $childId);
		$depthRefA = new ColumnReference($tableA, $depthId);


		$idRefB = new ColumnReference($tableB, $idColumnName);
		$parentRefB = new ColumnReference($tableB, $parentId);
		$childRefB = new ColumnReference($tableB, $childId);
		$depthRefB = new ColumnReference($tableB, $depthId);

		$idRefR = new ColumnReference($tableR, $idColumnName);
		$parentRefR = new ColumnReference($tableR, $parentId);
		$childRefR = new ColumnReference($tableR, $childId);

		$projections = [
			new Projection($idRef),
			new Projection($parentRef),
			new Projection($childRef),
			new Projection($depthRef),
		];

		if($this->schemaDef->isKeyScoped($keyId)) {
			$scopeColumn = $this->schemaDef->getKeyScopeColumn($keyId);
			$scopeRef = new ColumnReference($table, $this->fieldColumnToName($scopeColumn));
			$scopeRefA = new ColumnReference($tableA, $this->fieldColumnToName($scopeColumn));
			$scopeRefB = new ColumnReference($tableB, $this->fieldColumnToName($scopeColumn));
			$projections[] = new Projection($scopeRef);
			$scopeCondition = new BinaryOperation(
				new Equal(true),
				new Tuple([$scopeRefA, $scopeRefB]),
				new Tuple([$scopeRef, $scopeRef])
			);
		} else {
			$scopeCondition = new Constant(1);
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
						[new Projection($idRefA)], 
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
								),
								$scopeCondition
							]
						)
					)
				)
			)
		);


	
		$conditionD = new BinaryOperation(
			new Conjunction(), 
			new BinaryOperation(
				new NotEqual(), 
				$parentRef,
				$childRef
			),
			new Existence(new Select([new Projection($idRefR)], [$tableR], [], 
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

		return new CreateView($this->closureInvalidViewName($keyId), $select);
	}

	public function closureInvalidViewName($keyId) {
		return new Identifier(sprintf('_%s_invalid', $this->schemaDef->getKeyReflexivityTableName($keyId)));
	}

	private function buildClosureMissingsView($keyId) {

		$parentColumn = $this->schemaDef->getKeyReflexivityParentColumn($keyId);
		$childColumn = $this->schemaDef->getKeyReflexivityChildColumn($keyId);

		$tableA = new TableReference($this->closureTableName($keyId), new Identifier('a'));
		$tableB = new TableReference($this->closureTableName($keyId), new Identifier('b'));
		$tableT = new TableReference($this->closureTableName($keyId), new Identifier('t'));

		$parentId = $this->fieldColumnToName($parentColumn);
		$childId = $this->fieldColumnToName($childColumn);
		$idColumnName = new Identifier('id');
		$depthId = new Identifier('depth');
		
		$idRefA = new ColumnReference($tableA, $idColumnName);
		$parentRefA = new ColumnReference($tableA, $parentId);
		$childRefA = new ColumnReference($tableA, $childId);
		$depthRefA = new ColumnReference($tableA, $depthId);
		
		$idRefB = new ColumnReference($tableB, $idColumnName);
		$parentRefB = new ColumnReference($tableB, $parentId);
		$childRefB = new ColumnReference($tableB, $childId);
		$depthRefB = new ColumnReference($tableB, $depthId);
		
		$idRefT = new ColumnReference($tableT, $idColumnName);
		$parentRefT = new ColumnReference($tableT, $parentId);
		$childRefT = new ColumnReference($tableT, $childId);
		$depthRefT = new ColumnReference($tableT, $depthId);

		$projections = [];
		$projections[] = new Projection(new Constant(null), $idColumnName);

		if($this->schemaDef->isKeyScoped($keyId)) {
			$scopeColumn = $this->schemaDef->getKeyScopeColumn($keyId);
			$scopeRefA = new ColumnReference($tableA, $this->fieldColumnToName($scopeColumn));
			$scopeRefB = new ColumnReference($tableA, $this->fieldColumnToName($scopeColumn));
			$projections[] = new Projection($scopeRefA, $this->fieldColumnToName($scopeColumn));
			$scopeCondition = new BinaryOperation(new Equal(), $scopeRefA, $scopeRefB);
			$scopeConditionT = new Constant(1);
		} else {
			$scopeCondition = new Constant(1);
			$scopeConditionT = new Constant(1);
		}

		$projections[] = new Projection($parentRefA, $parentId);
		$projections[] = new Projection($childRefB, $parentId);
		$projections[] = new Projection($childRefB, $depthId);
		$projections[] = new Projection(new Constant("transitivity"), new Identifier('reason'));

		$condition = new AssociativeOperation(
			new Conjunction(),
			[
				new BinaryOperation(new NotEqual(), $idRefA, $idRefB),
				new BinaryOperation(new Equal(), $parentRefB, $childRefA),
				$scopeCondition,
				new UnaryOperation(new Negation(), new Existence(
					new Select(
						[new Projection($idRefT)], 
						[$tableT], [], 
						new BinaryOperation(
							new Conjunction(),
							$scopeConditionT,
							new BinaryOperation(
								new Equal(true), 
								new Tuple([$parentRefT, $childRefT, $depthRefT]), 
								new Tuple([$parentRefA, $childRefB, 
									new BinaryOperation(new Addition(), $depthRefA, $depthRefB)
								])
							)
						)
					)
				)),

			]
		);

		$tableM = new TableReference($this->closureTableName($keyId), new Identifier('m'));
		$tableR = new TableReference($this->closureTableName($keyId), new Identifier('r'));

		$idRefM = new ColumnReference($tableM, $idColumnName);
		$parentRefM = new ColumnReference($tableM, $parentId);
		$childRefM = new ColumnReference($tableM, $childId);
		$depthRefM = new ColumnReference($tableM, $depthId);

		$idRefR = new ColumnReference($tableR, $idColumnName);
		$parentRefR = new ColumnReference($tableR, $parentId);
		$childRefR = new ColumnReference($tableR, $childId);
		$depthRefR = new ColumnReference($tableR, $depthId);

		$unionProjects = [];
		$unionProjects[] = new Projection(new Constant(null), $idColumnName);

		if($this->schemaDef->isKeyScoped($keyId)) {
			$scopeRefA = new ColumnReference($tableA, $this->fieldColumnToName($scopeColumn));
			$scopeColumn = $this->schemaDef->getKeyScopeColumn($keyId);
			$projections[] = new Projection($scopeRefA, $this->fieldColumnToName($scopeColumn));
		}

		$unionProjects[] = new Projection($idRefM, $parentId);
		$unionProjects[] = new Projection($idRefM, $childId);
		$unionProjects[] = new Projection($depthRefM, $depthId);
		$unionProjects[] = new Projection(new Constant("reflexivity"), new Identifier('reason'));
		$union = new Select($unionProjects, [$tableM], [], new UnaryOperation(
			new Negation(),
			new Existence(new Select([new Projection($idRefR)], [$tableR], [], new BinaryOperation(
				new Equal(true),
				new Tuple([$parentRefR,$childRefR,$depthRefR]),
				new Tuple([$idRefM, $idRefM, new Constant(0)]),
			)))
		));


			// SELECT
			// 	NULL AS id,
			// 	m.{{closure_scope}} AS {{closure_scope}}, ---SCOPEONLY
			// 	id AS {{closure_parent}},
			// 	id AS {{closure_child}},
			// 	0 AS {{closure_depth}},
			// 	"reflexivity" AS reason
			// FROM
			// 	{{closure_table}} m
			// WHERE
			// 	NOT EXISTS (
			// 		SELECT id FROM {{closure_table}} r
			// 		WHERE (r.{{closure_parent}}, r.{{closure_child}}, r.{{closure_depth}})
			// 		IS (m.{{closure_id}}, m.{{closure_id}}, 0)
			// 	)

		$select = new Select(
			$projections, [$tableA, $tableB], [], $condition,
			[], NULL, 0, NULL, NULL,
			[$union]
		);

		/*
		SELECT
				NULL AS id,
			    a.{{closure_scope}} AS {{closure_scope}}, ---SCOPEONLY
				a.{{closure_parent}} AS {{closure_parent}},
				b.{{closure_child}} AS {{closure_child}},
				a.{{closure_depth}} + b.{{closure_depth}} AS {{closure_depth}},
				"transitivity" AS reason
			FROM
				{{closure_table}} a,
				{{closure_table}} b
			WHERE
				a.{{closure_id}} <> b.{{closure_id}}
				AND  ---SCOPEONLY
				a.{{closure_scope}} = b.{{closure_scope}}  ---SCOPEONLY
				AND
				b.{{closure_parent}} = a.{{closure_child}}
				AND
				NOT EXISTS (
					SELECT id
					FROM {{closure_table}} t WHERE
					(t.{{closure_parent}}, t.{{closure_child}}, t.{{closure_depth}})
					IS (a.{{closure_parent}}, b.{{closure_child}}, a.{{closure_depth}} + b.{{closure_depth}})
					AND ---SCOPEONLY
					t.{{closure_scope}} = a.{{closure_scope}} ---SCOPEONLY
				)
			UNION
			SELECT
				NULL AS id,
				m.{{closure_scope}} AS {{closure_scope}}, ---SCOPEONLY
				id AS {{closure_parent}},
				id AS {{closure_child}},
				0 AS {{closure_depth}},
				"reflexivity" AS reason
			FROM
				{{closure_table}} m
			WHERE
				NOT EXISTS (
					SELECT id FROM {{closure_table}} r
					WHERE (r.{{closure_parent}}, r.{{closure_child}}, r.{{closure_depth}})
					IS (m.{{closure_id}}, m.{{closure_id}}, 0)
				)*/
		
		return new CreateView($this->closureMissingViewName($keyId), $select);
	}

	public function closureMissingViewName($keyId) {
		return new Identifier(sprintf('_%s_missing', $this->schemaDef->getKeyReflexivityTableName($keyId)));
	}

	private function buildNormalizedOrderView($keyId) {
		$table = new TableReference($this->hierarchyViewName($keyId), new Identifier('h'));

		$orderRef = new ColumnReference($table, new Identifier('_order'));
		$idRef = new ColumnReference($table, new Identifier('_id'));
		$parentRef = new ColumnReference($table, new Identifier('_parent'));
		$scopeRef = new ColumnReference($table, new Identifier('_scope'));

		$select = new Select([
			new Projection($orderRef, new Identifier('_stored_order')),
			new Projection($idRef, new Identifier('_id')),
			new Projection($parentRef, new Identifier('_parent')),
			new Projection($scopeRef, new Identifier('_scope')),
			new Projection(new Windowing(
				new RankWindow(new RowNumber()),
				[$parentRef, $orderRef],
				[new Order($orderRef), new Order($idRef)]
			), new Identifier('_normalized_order'))
		], [$table]);
		
		return new CreateView($this->normalizedOrderViewName($keyId), $select);
	}

	public function normalizedOrderViewName($keyId) {
		return new Identifier(sprintf('_%s_hierarchy', $this->schemaDef->getKeyTableName($keyId)));
	}

}