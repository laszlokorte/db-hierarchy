<?php

namespace App\Hierarchy\Storage\Relational;


/*
getParentKey
loadAllClosureDefects
loadAllKeyNodes
loadAllRowOrder
loadChildKeyNodes
loadHierarchy
loadMoveTargets
loadNode
loadNodeField
loadNodesDirectParent
loadRootKeyNodes
loadRootNodes
*/
use App\Hierarchy\Schema\Definition\SchemaDefinition;

use App\Hierarchy\Storage\Relational\Algebra\Select;
use App\Hierarchy\Storage\Relational\Algebra\Projection;
use App\Hierarchy\Storage\Relational\Algebra\TableReference;
use App\Hierarchy\Storage\Relational\Algebra\Value\BinaryOperation;
use App\Hierarchy\Storage\Relational\Algebra\Value\ColumnReference;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Comparison\NotEqual;

class QueryBuilder {
	public function __construct(private SchemaDefinition $schemaDef, private Naming $naming) {
		
	}

	public function getSelectForFindNodes(string $keyId) {
		
	}

	public function getSelectForFindNode(string $keyId) {
		
	}

	public function getSelectForFindNodeField(string $keyId, string $fieldId) {
		
	}

	public function getSelectForFindNodeChildren(string $keyId, string $childKeyId) {
		
	}

	public function getSelectForFindNodeDirectParent(string $keyId) {
		
	}

	public function getSelectForFindNodeReflexiveParents(string $keyId, ?int $limit = NULL) {
		
	}

	public function getSelectForFindNodeParents(string $keyId, ?int $limit = NULL) {
		
	}

	public function getDiagnosableKeys() {
		return array_filter($this->schemaDef->getAllKeyIdsTopological(), 
			fn($keyId) => $this->schemaDef->isKeyReflexive($keyId) || $this->schemaDef->isKeyOrdered($keyId)
		);
	}

	public function getDiagnosisQueriesForKey($keyId) {
		$result = [];

		if($this->schemaDef->isKeyReflexive($keyId)) {
			$result['missing'] = $this->getSelectForFindKeyClosureMissings($keyId);
			$result['invalid'] = $this->getSelectForFindKeyClosureInvalids($keyId);
		}

		if($this->schemaDef->isKeyOrdered($keyId)) {
			$result['order'] = $this->getSelectForFindKeyOrderNotNormalized($keyId);
		}

		return $result;
	}

	public function getSelectForFindKeyClosureMissings($keyId) {
		$missingView = new TableReference($this->naming->closureMissingViewName($keyId));
		return new Select([
			new Projection(new ColumnReference($missingView, $this->naming->closureMissingIdColumn($keyId))),
			new Projection(new ColumnReference($missingView, $this->naming->closureMissingParentColumn($keyId))),
			new Projection(new ColumnReference($missingView, $this->naming->closureMissingChildColumn($keyId))),
			new Projection(new ColumnReference($missingView, $this->naming->closureMissingDepthColumn($keyId))),
			new Projection(new ColumnReference($missingView, $this->naming->closureMissingReasonColumn($keyId))),
		], [$missingView]);
	}

	public function getSelectForFindKeyClosureInvalids($keyId) {
		$invalidView = new TableReference($this->naming->closureInvalidViewName($keyId));
		return new Select([
			new Projection(new ColumnReference($invalidView, $this->naming->closureInvalidIdColumn($keyId))),
			new Projection(new ColumnReference($invalidView, $this->naming->closureInvalidParentColumn($keyId))),
			new Projection(new ColumnReference($invalidView, $this->naming->closureInvalidChildColumn($keyId))),
			new Projection(new ColumnReference($invalidView, $this->naming->closureInvalidDepthColumn($keyId))),
		], [$invalidView]);
	}

	public function getSelectForFindKeyOrderNotNormalized(string $keyId) {
		$orderView = new TableReference($this->naming->normalizedOrderViewName($keyId));
		$orderCondition = new BinaryOperation(
			new NotEqual(),
			new ColumnReference($orderView, $this->naming->normalizedOrderNormalizedColumnName($keyId)),
			new ColumnReference($orderView, $this->naming->normalizedOrderStoredColumnName($keyId))
		);

		return new Select([
			new Projection(new ColumnReference($orderView, $this->naming->normalizedOrderStoredColumnName($keyId))),
			new Projection(new ColumnReference($orderView, $this->naming->normalizedOrderNormalizedColumnName($keyId))),
			new Projection(new ColumnReference($orderView, $this->naming->normalizedOrderIdColumnName($keyId))),
			new Projection(new ColumnReference($orderView, $this->naming->normalizedOrderParentColumnName($keyId))),
			new Projection(new ColumnReference($orderView, $this->naming->normalizedOrderScopeColumnName($keyId))),
		], [$orderView], [], $orderCondition);
	}

	public function getSelectForFindDefectsForNode(string $keyId) {

	}
}
