<?php

namespace App\Hierarchy\Storage\Relational\Service;

use App\Hierarchy\Storage\Relational\Naming;
use App\Hierarchy\Storage\Relational\Quirks;

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
use App\Hierarchy\Schema\Definition\ReferenceCoding;
use App\Hierarchy\Schema\Definition\ReferenceCodingCascade;
use App\Hierarchy\Schema\Definition\StorageCoding;

class InstallationCommandBuilder {
	public const CLOSURE_TABLE_PK_TYPE = 'INTEGER';
	public const CLOSURE_TABLE_DEPTH_TYPE = 'INTEGER';

	public function __construct(private SchemaDefinition $schemaDef, private Naming $naming, private Quirks $quirks) {
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
		$pkColumnDef = $this->schemaDef->getKeyIdentityColumn($keyId);
		$pkColumnName = $this->nodeTablePKName($keyId);
		$pkColumn = $this->fieldColumnToTableColumn($pkColumnDef, true);
		
		$columns = [];
		$uniques = [];
		$foreignKeys = [];

		if($this->schemaDef->isKeyOrdered($keyId)) {
			$columns[] = $this->fieldColumnToTableColumn($this->schemaDef->getKeyOrderColumn($keyId));
		}

		if($this->schemaDef->isKeyScoped($keyId)) {
			$scopeColumn = $this->schemaDef->getKeyScopeColumn($keyId);
			$columns[] = $this->fieldColumnToTableColumn($scopeColumn);

			$isolations = $this->schemaDef->getKeyIsolations($keyId);
			if(!empty($isolations)) {
				foreach ($isolations as $isoKey) {
					$isoColumn = $this->schemaDef->getKeyIsolationColumn($isoKey);
					$columns[] = $this->fieldColumnToTableColumn($isoColumn);
				}

				$uniques[] = array_merge(
					array_reverse(array_map(fn($iso) => $this->naming->nodeOwnIsolationColumnName($iso), $isolations)),
					$this->schemaDef->isKeyScopeIsolating($keyId) ? [
						$this->naming->nodeOwnScopeColumnName($keyId)
					] : [],
					[$pkColumnName]
				);

				foreach ($isolations as $iso) {
					$uniques[] = [
						$iso === $this->schemaDef->getKeyScopeId($keyId) ?
						$this->naming->nodeOwnScopeColumnName($keyId) :
						$this->naming->nodeOwnIsolationColumnName($iso)
						,
						$pkColumnName
					];
				}
			}

			if(!$this->quirks->noDeferredFK()) {
				$uniques[] = [$this->nodeOwnScopeColumnName($keyId), $pkColumnName];
			}

			foreach($this->schemaDef->getReferencingKeys($keyId) AS $sourceKey) {

				$commonIsolation = $this->schemaDef->getCommonIsolation($sourceKey, $keyId);

				if($commonIsolation) {
					if($commonIsolation[1] === $keyId) {
						$uniques[] = [$this->naming->nodeOwnScopeColumnName($commonIsolation[1]), $this->nodeTablePKName($keyId)];
					} else {
						$uniques[] = [$this->naming->nodeOwnIsolationColumnName($commonIsolation[1]), $this->nodeTablePKName($keyId)];
					}
				}
			}

		}


		if($this->schemaDef->isKeySingleton($keyId)) {

			if(!$this->schemaDef->isKeyReflexive($keyId)) {
				$singletonKey = [];
				if($this->schemaDef->isKeyScoped($keyId)) {
					$singletonKey[] = $this->nodeOwnScopeColumnName($keyId);
				} else {
					$columns[] = new TableColumn(
						new Identifier($this->schemaDef->getKeySingletonColumnName($keyId)),
						'char(1)',
						true,
						new Constant('x')
					);
					$singletonKey[] = new Identifier($this->schemaDef->getKeySingletonColumnName($keyId));
				}


				$uniques[] = $singletonKey;
			}
		}

		foreach ($this->fieldsColumns($keyId) as $fieldColumn) {
			$columns[] = $this->fieldColumnToTableColumn($fieldColumn);
			
			if($fieldColumn->isReference()) {
				$targetKeyName = $fieldColumn->getCoding()->getTarget();
				$ownColumnName = $this->fieldColumnToName($fieldColumn);
				$targetColumnName = $this->nodeTablePKName($keyId);

				$targetTableName = $this->nodeTableName($targetKeyName);

				switch ($fieldColumn->getCoding()->getCascade()) {
					case ReferenceCodingCascade::FOLLOW:
						$onDelete = ForeignKey::CASCADE;
						break;
					case ReferenceCodingCascade::CLEAR:
						$onDelete = ForeignKey::SET_NULL;
						break;
					case ReferenceCodingCascade::RESTRICT:
						$onDelete = ForeignKey::RESTRICT;
						break;
					default:
						throw new \Exception("Unexpected cascade value");
				}

				$ownCols = [$ownColumnName];
				$otherCols = [$targetColumnName];

				$commonIsolation = $this->schemaDef->getCommonIsolation($keyId, $targetKeyName);


				if($commonIsolation) {					
					if($commonIsolation[0] === $keyId) {
						array_unshift($ownCols, $this->naming->nodeOwnScopeColumnName($commonIsolation[0]));
					} else {
						array_unshift($ownCols, $this->naming->nodeOwnIsolationColumnName($commonIsolation[0]));
					}

					if($commonIsolation[1] === $targetKeyName) {
						array_unshift($otherCols, $this->naming->nodeOwnScopeColumnName($commonIsolation[1]));
					} else {
						array_unshift($otherCols, $this->naming->nodeOwnIsolationColumnName($commonIsolation[1]));
					}

				}

				$foreignKeys[] = new ForeignKey(
					$ownCols, 
					$targetTableName, 
					$otherCols,
					$onDelete
				);
			}
		}	

		$uniqueFieldsIds = array_filter(
			$this->schemaDef->getKeyFieldIds($keyId), 
			fn($fieldId) => $this->schemaDef->isKeyFieldUnique($keyId, $fieldId)
		);

		// TODO: unique keys do not work for reflexive nodes
		if(!$this->schemaDef->isKeyReflexive($keyId)) {
			foreach ($uniqueFieldsIds as $ufid) {
				$uniqueFieldCombi = array_map(fn($c) => $this->fieldColumnToName($c), $this->getFieldColumns($keyId, $ufid));

				if($this->schemaDef->isKeyScoped($keyId)) {
					$uniqueFieldCombi[] = $this->nodeOwnScopeColumnName($keyId);
				}

				$uniques[] = $uniqueFieldCombi;
			}
		}


		if($this->schemaDef->isKeyScoped($keyId)) {
			$ownColumnName = $this->nodeOwnScopeColumnName($keyId);
			$targetColumnName = $this->scopeTablePKName($keyId);

			$targetTableName = $this->scopeTableName($keyId);

			$ownColumns = [$ownColumnName];
			$targetColumns = [$targetColumnName];

			$isolations = $this->schemaDef->getKeyIsolations($keyId);
			if(!empty($isolations)) {
				foreach ($isolations as $i => $isoKey) {
					array_unshift($ownColumns, $this->naming->nodeOwnIsolationColumnName($isoKey));

					if($isoKey === $this->schemaDef->getKeyScopeId($keyId)) {
						array_unshift($targetColumns, $this->naming->nodeOwnScopeColumnName($isoKey));
					} else {
						array_unshift($targetColumns, $this->naming->nodeOwnIsolationColumnName($isoKey));
					}

				}
			}

			$foreignKeys[] = new ForeignKey(
				$ownColumns, 
				$targetTableName, 
				$targetColumns,
				ForeignKey::RESTRICT
			);
		}

		return new CreateTable($tableName, $pkColumn, $columns, $uniques, $foreignKeys);
	}



	private function fieldsColumns($keyId) {
		return array_merge([], ...array_map(fn($fieldId) => $this->getFieldColumns($keyId, $fieldId), $this->schemaDef->getKeyFieldIds($keyId)));
	}

	private function getFieldColumns($keyId, $fieldId) {
		$fieldType = $this->schemaDef->getKeyFieldType($keyId, $fieldId);
		$options = $this->schemaDef->getKeyFieldOptions($keyId, $fieldId);
		$required = $this->schemaDef->isKeyFieldRequired($keyId, $fieldId);

		return $fieldType->getColumns($fieldId, $required, $options);
	}

	private function fieldColumnToTableColumn(ColumnDefinition $columnDefinition, $keepSerial = false) {
		return new TableColumn(
			$this->fieldColumnToName($columnDefinition),
			$this->columnCodingToSqlType($columnDefinition->getCoding()),
			$columnDefinition->isNullable(),
			$columnDefinition->getDefault() !== null ?
			new Constant($columnDefinition->getDefault()) : null,
			$keepSerial && $columnDefinition->getCoding() instanceof StorageCoding && $columnDefinition->getCoding()->getType() === 'SERIAL'
		);
	}

	private function columnCodingToSqlType(StorageCoding|ReferenceCoding $storageCoding) {
		if($storageCoding instanceof ReferenceCoding) {
			$targetKey = $storageCoding->getTarget();

			return $this->columnCodingToSqlType($this->schemaDef->getKeyIdentityColumn($targetKey)->getCoding());
		} else {
			switch ($storageCoding->getType()) {
				case 'INTEGER':
					return 'INTEGER SIGNED';
				case 'SERIAL':
					return 'SERIAL';
				case 'UUID':
					return 'BINARY(16)';
				default:
					return 'VARCHAR(120)';
			}
		}
	}

	private function fieldColumnToName(ColumnDefinition $columnDefinition) {
		return $this->naming->fieldColumnToName($columnDefinition);
	}

	private function nodeTableName($keyId) {
		return $this->naming->nodeTableName($keyId);
	}

	private function nodeTablePKName($keyId) {
		return $this->naming->nodeTablePKName($keyId);
	}

	private function closureParentColumnName($keyId) {
		return $this->naming->closureParentColumnName($keyId);
	}

	private function closureChildColumnName($keyId) {
		return $this->naming->closureChildColumnName($keyId);
	}

	private function scopeTablename($keyId) {
		return $this->naming->scopeTablename($keyId);
	}

	private function scopeTablePKName($keyId) {
		return $this->naming->scopeTablePKName($keyId);
	}

	private function nodeOwnScopeColumnName($keyId) {
		return $this->naming->nodeOwnScopeColumnName($keyId);
	}

	private function closureTableName($keyId) {
		return $this->naming->closureTableName($keyId);
	}

	private function closureTablePkName($keyId) {
		return $this->naming->closureTablePkName($keyId);
	}

	private function closureTableDepthName($keyId) {
		return $this->naming->closureTableDepthName($keyId);
	}

	private function buildClosureTable($keyId) {
		$scopeColumnNames = [];
		$pkColumnName = $this->closureTablePkName($keyId);
		$pkColumn = new TableColumn($pkColumnName, self::CLOSURE_TABLE_PK_TYPE, false, null, true);
		
		$columns = [
		];

		$targetTableName = $this->nodeTableName($keyId);
		$parentColumn = $this->schemaDef->getKeyReflexivityParentColumn($keyId);
		$childColumn = $this->schemaDef->getKeyReflexivityChildColumn($keyId);

		if($this->schemaDef->isKeyScoped($keyId)) {
			$scopeColumn = $this->schemaDef->getKeyScopeColumn($keyId);
			$scopeColumnNames[] = $this->nodeOwnScopeColumnName($keyId);

			$columns[] = $this->fieldColumnToTableColumn($scopeColumn);
		}

		$columns[] = $this->fieldColumnToTableColumn($parentColumn);
		$columns[] = $this->fieldColumnToTableColumn($childColumn);
		$depthId = $this->closureTableDepthName($keyId);
		$columns[] = new TableColumn($depthId, self::CLOSURE_TABLE_DEPTH_TYPE, false, null);


		$targetColumnName = $this->nodeTablePKName($keyId);
		$parentColumnName = $this->closureParentColumnName($keyId);
		$childColumnName = $this->closureChildColumnName($keyId);

		$uniques = [
			[$childColumnName, $parentColumnName],
			[$childColumnName, $depthId],
		];

		if($this->schemaDef->isKeyScoped($keyId) && $this->schemaDef->isKeySingleton($keyId)) {
			$uniques[] = array_merge($scopeColumnNames, [$depthId]);
		}

		if($this->schemaDef->isKeySingleton($keyId)) {
			if($this->schemaDef->isKeyScoped($keyId)) {
				$uniques[] = array_merge($scopeColumnNames, [$depthId]);
			} else {
				$uniques[] = [$depthId];
			}
		}

		$foreignKeys = [
			new ForeignKey(
				$this->quirks->noDeferredFK() ? [$childColumnName] :
				array_merge($scopeColumnNames, [$childColumnName]), 
				$targetTableName, 
				$this->quirks->noDeferredFK() ? [$targetColumnName] :
				array_merge($scopeColumnNames, [$targetColumnName]),
				ForeignKey::RESTRICT
			),
			new ForeignKey(
				$this->quirks->noDeferredFK() ? [$parentColumnName] :
				array_merge($scopeColumnNames, [$parentColumnName]), 
				$targetTableName, 
				$this->quirks->noDeferredFK() ? [$targetColumnName] :
				array_merge($scopeColumnNames, [$targetColumnName]),
				ForeignKey::RESTRICT
			)
		];
		
		return new CreateTable($this->closureTableName($keyId), $pkColumn, $columns, $uniques, $foreignKeys);
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
			$scopeProjection = new Projection($scopeRef, $this->naming->hierarchyScopeColumnName($keyId));

			$joins[] = new Join($tableScope, 
				new BinaryOperation(
					new Equal(),
					$scopeRef,
					$idRefScope
				)
			,'INNER');
		} else {
			$scopeProjection = new Projection(new Constant(null), $this->naming->hierarchyScopeColumnName($keyId));
		}

		if($this->schemaDef->isKeyReflexive($keyId)) {

			$parentColumn = $this->schemaDef->getKeyReflexivityParentColumn($keyId);
			$childColumn = $this->schemaDef->getKeyReflexivityChildColumn($keyId);

			$parentId = $this->closureParentColumnName($keyId);
			$childId = $this->closureChildColumnName($keyId);
			$depthId = $this->closureTableDepthName($keyId);

			$tableParent = new TableReference($this->nodeTableName($keyId), new Identifier('p'));
			$tableReflexive = new TableReference($this->closureTableName($keyId), new Identifier('r'));
			$tableClosure = new TableReference($this->closureTableName($keyId), new Identifier('c'));
			
			$idRefParent = new ColumnReference($tableParent, $this->nodeTablePKName($keyId));
			$parentRefClosure = new ColumnReference($tableClosure, $parentId);
			$childRefClosure = new ColumnReference($tableClosure, $childId);
			$depthRefClosure = new ColumnReference($tableClosure, $depthId);

			$parentRefRelexive = new ColumnReference($tableReflexive, $parentId);
			$childRefRelexive = new ColumnReference($tableReflexive, $childId);
			$depthRefRelexive = new ColumnReference($tableReflexive, $depthId);


			$parentProjection = new Projection($parentRefClosure, $this->naming->hierarchyParentColumnName($keyId));
	
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
			$parentProjection = new Projection(new Constant(null), $this->naming->hierarchyParentColumnName($keyId));
		}

		if($this->schemaDef->isKeyOrdered($keyId)) {
			$orderColumn = $this->schemaDef->getKeyOrderColumn($keyId);
			$orderRef = new ColumnReference($table, $this->naming->orderColumnName($keyId));

			$orderProjection = new Projection($orderRef, $this->naming->hierarchyOrderColumnName($keyId));

			$orders[] = new Order($orderRef, $this->schemaDef->getKeyOrderDirection($keyId) === 'ASC');
		} else {
			$orderProjection = new Projection(new Constant(null), $this->naming->hierarchyOrderColumnName($keyId));
		}

		$projections = [
			$scopeProjection,
			$parentProjection,
			$orderProjection,
			new Projection($idRef, $this->naming->hierarchyIdColumnName($keyId)),
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
		return $this->naming->hierarchyViewName($keyId);
	}

	private function buildClosureInvalidsView($keyId) {
		$values = [];
		$values[] = new Projection(new Constant(1));
		$select = new Select($values);

		$parentColumn = $this->schemaDef->getKeyReflexivityParentColumn($keyId);
		$childColumn = $this->schemaDef->getKeyReflexivityChildColumn($keyId);

		$parentId = $this->closureParentColumnName($keyId);
		$childId = $this->closureChildColumnName($keyId);

		$table = new TableReference($this->closureTableName($keyId), new Identifier('t'));
		$tableA = new TableReference($this->closureTableName($keyId), new Identifier('a'));
		$tableB = new TableReference($this->closureTableName($keyId), new Identifier('b'));
		$tableR = new TableReference($this->closureTableName($keyId), new Identifier('r'));

		$idColumnName = $this->closureTablePkName($keyId);
		$depthId = $this->closureTableDepthName($keyId);
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
			new Projection($idRef, $this->naming->closureInvalidIdColumn($keyId)),
			new Projection($parentRef, $this->naming->closureInvalidParentColumn($keyId)),
			new Projection($childRef, $this->naming->closureInvalidChildColumn($keyId)),
			new Projection($depthRef, $this->naming->closureInvalidDepthColumn($keyId)),
		];

		if($this->schemaDef->isKeyScoped($keyId)) {
			$scopeColumn = $this->schemaDef->getKeyScopeColumn($keyId);
			$scopeRef = new ColumnReference($table, $this->nodeOwnScopeColumnName($keyId));
			$scopeRefA = new ColumnReference($tableA, $this->nodeOwnScopeColumnName($keyId));
			$scopeRefB = new ColumnReference($tableB, $this->nodeOwnScopeColumnName($keyId));
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
									new Tuple([$parentRefA, $childRefB])
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

		$tableX = new TableReference($this->nodeTableName($keyId), new Identifier('x'));
		$tableY = new TableReference($this->nodeTableName($keyId), new Identifier('y'));

		if($this->schemaDef->isKeyScoped($keyId)) {
			$joins = [
				new Join($tableX, 
					new BinaryOperation(
						new Equal(), 
						new Tuple([
							new ColumnReference($tableX, $this->naming->nodeOwnScopeColumnName($keyId)), 
							new ColumnReference($tableX, $this->naming->nodeTablePKName($keyId)), 
						]),
						new Tuple([
							$scopeRef,
							$parentRef,
						])
					), 
					'LEFT'
				),
				new Join($tableY, 
					new BinaryOperation(
						new Equal(), 
						new Tuple([
							new ColumnReference($tableY, $this->naming->nodeOwnScopeColumnName($keyId)), 
							new ColumnReference($tableY, $this->naming->nodeTablePKName($keyId)), 
						]),
						new Tuple([
							$scopeRef,
							$childRef,
						])
					), 
					'LEFT'
				)
			];
		} else {
			$joins = [
				new Join($tableX, 
					new BinaryOperation(
						new Equal(), 
						new ColumnReference($tableX, $this->naming->nodeTablePKName($keyId)), 
						$parentRef,
					), 
					'LEFT'
				),
				new Join($tableY, 
					new BinaryOperation(
						new Equal(), 
						new ColumnReference($tableY, $this->naming->nodeTablePKName($keyId)), 
						$childRef,
					), 
					'LEFT'
				)
			];
		}


		$conditionE = new BinaryOperation(
			new Disjunction(),
			new BinaryOperation(
				new Equal(true),
				new ColumnReference($tableX, $this->naming->nodeTablePKName($keyId)),
				new Constant(null)
			),
			new BinaryOperation(
				new Equal(true),
				new ColumnReference($tableY, $this->naming->nodeTablePKName($keyId)),
				new Constant(null)
			),
		);


		$conditions = [$conditionA, $conditionB, $conditionC, $conditionD, $conditionE];
		$condition = new AssociativeOperation(
			new Disjunction(),
			$conditions
		);

		$select = new Select($projections, [
			$table
		], $joins, $condition);

		return new CreateView($this->closureInvalidViewName($keyId), $select);
	}

	public function closureInvalidViewName($keyId) {
		return $this->naming->closureInvalidViewName($keyId);
	}

	private function buildClosureMissingsView($keyId) {

		$parentColumn = $this->schemaDef->getKeyReflexivityParentColumn($keyId);
		$childColumn = $this->schemaDef->getKeyReflexivityChildColumn($keyId);

		$tableA = new TableReference($this->closureTableName($keyId), new Identifier('a'));
		$tableB = new TableReference($this->closureTableName($keyId), new Identifier('b'));
		$tableT = new TableReference($this->closureTableName($keyId), new Identifier('t'));

		$parentId = $this->closureParentColumnName($keyId);
		$childId = $this->closureChildColumnName($keyId);
		$idColumnName = $this->closureTablePkName($keyId);
		$depthId = $this->closureTableDepthName($keyId);
		
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
		$projections[] = new Projection(new Constant(null), $this->naming->closureMissingIdColumn($keyId));

		if($this->schemaDef->isKeyScoped($keyId)) {
			$scopeColumn = $this->schemaDef->getKeyScopeColumn($keyId);
			$scopeRefA = new ColumnReference($tableA, $this->nodeOwnScopeColumnName($keyId));
			$scopeRefB = new ColumnReference($tableA, $this->nodeOwnScopeColumnName($keyId));
			$projections[] = new Projection($scopeRefA, $this->nodeOwnScopeColumnName($keyId));
			$scopeCondition = new BinaryOperation(new Equal(), $scopeRefA, $scopeRefB);
			$scopeConditionT = new Constant(1);
		} else {
			$scopeCondition = new Constant(1);
			$scopeConditionT = new Constant(1);
		}

		$projections[] = new Projection($parentRefA, $this->naming->closureMissingParentColumn($keyId));
		$projections[] = new Projection($childRefB, $this->naming->closureMissingChildColumn($keyId));
		$projections[] = new Projection(
			new BinaryOperation(
				new Addition(),
				$depthRefA,
				$depthRefB
			)
			, $this->naming->closureMissingDepthColumn($keyId));
		$projections[] = new Projection(new Constant("transitivity"), $this->naming->closureMissingReasonColumn($keyId));

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

		$tableX = new TableReference($this->nodeTableName($keyId), new Identifier('x'));
		$tableY = new TableReference($this->closureTableName($keyId), new Identifier('y'));

		$unionProjects = [];
		$unionProjects[] = new Projection(new Constant(null), $idColumnName);

		if($this->schemaDef->isKeyScoped($keyId)) {
			$scopeRefX = new ColumnReference($tableX, $this->nodeOwnScopeColumnName($keyId));
			$scopeColumn = $this->schemaDef->getKeyScopeColumn($keyId);
			$unionProjects[] = new Projection($scopeRefX, $this->nodeOwnScopeColumnName($keyId));
		}

		$unionProjects[] = new Projection(new ColumnReference($tableX, $this->naming->nodeTablePKName($keyId)), $parentId);
		$unionProjects[] = new Projection(new ColumnReference($tableX, $this->naming->nodeTablePKName($keyId)), $childId);
		$unionProjects[] = new Projection(new Constant(0), $depthId);
		$unionProjects[] = new Projection(new Constant("existence"), new Identifier('reason'));

		$union = new Select($unionProjects, [$tableX], [
			new Join($tableY, 
				new BinaryOperation(
					new Equal(), 
					new Tuple([
						new ColumnReference($tableX, $this->naming->nodeTablePKName($keyId)),
						new ColumnReference($tableX, $this->naming->nodeTablePKName($keyId)),
						new Constant(0),
					]), 
					new Tuple([
						new ColumnReference($tableY, $this->naming->closureParentColumnName($keyId)),
						new ColumnReference($tableY, $this->naming->closureChildColumnName($keyId)),
						new ColumnReference($tableY, $this->naming->closureTableDepthName($keyId)),
					]),
				), 
				'LEFT'
			)
		], new BinaryOperation(
			new Equal(true), 
			new Constant(null), 
			new ColumnReference($tableY, $this->naming->closureTablePkName($keyId))
		));

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
		return $this->naming->closureMissingViewName($keyId);
	}

	private function buildNormalizedOrderView($keyId) {
		$table = new TableReference($this->hierarchyViewName($keyId), new Identifier('h'));

		$idRef = new ColumnReference($table, $this->naming->hierarchyIdColumnName($keyId));
		$orderRef = new ColumnReference($table, $this->naming->hierarchyOrderColumnName($keyId));
		$parentRef = new ColumnReference($table, $this->naming->hierarchyParentColumnName($keyId));
		$scopeRef = new ColumnReference($table, $this->naming->hierarchyScopeColumnName($keyId));

		$select = new Select([
			new Projection($orderRef, $this->naming->normalizedOrderStoredColumnName($keyId)),
			new Projection($idRef, $this->naming->normalizedOrderIdColumnName($keyId)),
			new Projection($parentRef, $this->naming->normalizedOrderParentColumnName($keyId)),
			new Projection($scopeRef, $this->naming->normalizedOrderScopeColumnName($keyId)),
			new Projection(new Windowing(
				new RankWindow(new RowNumber()),
				[$parentRef, $scopeRef],
				[new Order($orderRef), new Order($idRef)]
			), $this->naming->normalizedOrderNormalizedColumnName($keyId))
		], [$table]);
		
		return new CreateView($this->normalizedOrderViewName($keyId), $select);
	}

	public function normalizedOrderViewName($keyId) {
		return $this->naming->normalizedOrderViewName($keyId);
	}

}