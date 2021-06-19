<?php

namespace App\Hierarchy\Storage\Relational;

use App\Hierarchy\Schema\Definition\SchemaDefinition;

use App\Hierarchy\Storage\Relational\Algebra\TableReference;
use App\Hierarchy\Storage\Relational\Algebra\Value\ColumnReference;
use App\Hierarchy\Storage\Relational\Algebra\Value\Constant;
use App\Hierarchy\Storage\Relational\Algebra\Value\UnaryOperation;
use App\Hierarchy\Storage\Relational\Algebra\Value\ElementOf;
use App\Hierarchy\Storage\Relational\Algebra\Select;
use App\Hierarchy\Storage\Relational\Algebra\Projection;
use App\Hierarchy\Storage\Relational\Algebra\Insert;
use App\Hierarchy\Storage\Relational\Algebra\Update;
use App\Hierarchy\Storage\Relational\Algebra\Delete;
use App\Hierarchy\Storage\Relational\Algebra\Setter;

class CommandBuilder  {

	public function __construct(private SchemaDefinition $schemaDef, private Naming $naming) {
	}

	public function getCommandForCreateNode(string $keyId) {
		
	}

	public function getCommandForUpdateNode(string $keyId) {
		
	}

	public function getCommandForDeleteNode(string $keyId) {
		
	}

	public function getCommandForMoveNode(string $keyId) {
		
	}

	public function getCommandForRepairKey(string $keyId) {
		$result = [];

		if($this->schemaDef->isKeyReflexive($keyId)) {
			$result['invalid'] = $this->getDeleteForClosureRepair($keyId);
			$result['missing'] = $this->getInsertForClosureRepair($keyId);
		}

		if($this->schemaDef->isKeyOrdered($keyId)) {
			$result['order'] = $this->getUpdateForOrderRepair($keyId);
		}

		return $result;
	}

	public function getRepairableKeys() {
		return array_filter($this->schemaDef->getAllKeyIdsTopological(), 
			fn($keyId) => $this->schemaDef->isKeyReflexive($keyId) || $this->schemaDef->isKeyOrdered($keyId)
		);
	}

	private function getDeleteForClosureRepair($keyId) {
		$closureTable = new TableReference($this->naming->closureTableName($keyId));
		$invalidView = new TableReference($this->naming->closureInvalidViewName($keyId));
		$invalidViewId = new ColumnReference($invalidView, $this->naming->closureInvalidIdColumn($keyId));

		$idColumn = new ColumnReference($closureTable, $this->naming->closureTablePkName($keyId));

		return new Delete($closureTable, 
			new ElementOf(
				$idColumn,
				new Select([new Projection($invalidViewId)], [$invalidView])
			)
		);
	}

	private function getInsertForClosureRepair($keyId) {
		$closureTableName = $this->naming->closureTableName($keyId);
		$missingView = new TableReference($this->naming->closureMissingViewName($keyId));

		return new Insert(
			$closureTableName,
			[
				$this->naming->closureTablePkName($keyId),
				$this->naming->closureParentColumnName($keyId),
				$this->naming->closureChildColumnName($keyId),
				$this->naming->closureTableDepthName($keyId),
			],
			new Select([
				new Projection(new ColumnReference($missingView, $this->naming->closureMissingIdColumn($keyId))),
				new Projection(new ColumnReference($missingView, $this->naming->closureMissingParentColumn($keyId))),
				new Projection(new ColumnReference($missingView, $this->naming->closureMissingChildColumn($keyId))),
				new Projection(new ColumnReference($missingView, $this->naming->closureMissingDepthColumn($keyId))),
			], [$missingView])
		);
	}

	private function getUpdateForOrderRepair($keyId) {
		$table = new TableReference($this->naming->nodeTableName($keyId));
		$orderColumn = new ColumnReference($table, $this->naming->orderColumnName($keyId));

		return new Update($table, [
			new Setter($orderColumn, new Constant(0)),
		]);
	}

}