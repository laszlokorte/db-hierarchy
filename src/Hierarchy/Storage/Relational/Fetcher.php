<?php

namespace App\Hierarchy\Storage\Relational;

use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Storage\Relational\Dialect\DialectInterface;
use App\Hierarchy\Storage\Relational\Algebra\Value\Parameter;
use App\Hierarchy\Storage\Relational\Algebra\Value\Constant;
use App\Hierarchy\Data;

use Doctrine\DBAL\Connection;

class Fetcher {
	private $transactionDepth = 0;

	public function __construct(private SchemaDefinition $schemaDef, private QueryBuilder $queryBuilder, private Connection $connection, private DialectInterface $dialect) {

	}

	public function findRootNodes(string $keyId) : Data\NodeCollection {
		$select = $this->queryBuilder->getSelectForFindNodes($keyId, null, new Constant(null));

		$this->beginTransaction();
		$stmt = $this->connection->prepare($this->dialect->selectToString($select));
		$stmt->execute();
    	$this->commitTransaction();
		$rows = $stmt->fetchAllAssociativeIndexed();

		return new Data\NodeCollection(
			$keyId,
			$rows,
			NULL,
			NULL
		);
	}

	public function findAllNodes(string $keyId) : Data\NodeCollection {
		$select = $this->queryBuilder->getSelectForFindNodes($keyId, null, null);

		$this->beginTransaction();
		$stmt = $this->connection->prepare($this->dialect->selectToString($select));
		$stmt->execute();
    	$this->commitTransaction();
		$rows = $stmt->fetchAllAssociativeIndexed();

		return new Data\NodeCollection(
			$keyId,
			$rows,
			NULL,
			NULL
		);
	}

	public function findAllRootNodes() : Data\MultiCollection {
		$groupedRows = [];
		
		$this->beginTransaction();

		foreach ($this->schemaDef->getRootScopeKeyIds() as $keyId) {
			$select = $this->queryBuilder->getSelectForFindNodes($keyId, new Constant(null), new Constant(null));
			$stmt = $this->connection->prepare($this->dialect->selectToString($select));
			$stmt->execute();
			$groupedRows[$keyId] = $stmt->fetchAllAssociativeIndexed();
		}

    	$this->commitTransaction();

		return new Data\MultiCollection(
			null, 
			null, 
			$groupedRows, 
			null,
			null
		);
	}

	public function findHierarchyNodes($keyId) : Data\NodeTree {
		$select = $this->queryBuilder->getSelectForFindHierarchy($keyId, null, null);

		$this->beginTransaction();
		$stmt = $this->connection->prepare($this->dialect->selectToString($select));
		$stmt->execute();
    	$this->commitTransaction();
		$rows = $stmt->fetchAll(\PDO::FETCH_GROUP);

		return new Data\NodeTree(
			$keyId,
			$rows,
			NULL,
			NULL
		);
	}

	public function findAllHierarchyNodes() : Data\MultiTree {
		$groupedRows = [];
		
		$this->beginTransaction();

		foreach ($this->schemaDef->getAllKeyIds() as $keyId) {
			$select = $this->queryBuilder->getSelectForFindHierarchy($keyId, null, null);
			$stmt = $this->connection->prepare($this->dialect->selectToString($select));
			$stmt->execute();
			$groupedRows[$keyId] = $stmt->fetchAll(\PDO::FETCH_GROUP);
		}

    	$this->commitTransaction();

		return new Data\MultiTree(
			$this->schemaDef->getAllKeyIds(),
			$groupedRows
		);
	}

	public function findNode(string $keyId, string $nodeId) : ?Data\Node {
		$param = new Parameter('nodeId');
		$select = $this->queryBuilder->getSelectForFindNode($keyId, $param);

		$this->beginTransaction();
		$stmt = $this->connection->prepare($this->dialect->selectToString($select));
		$stmt->bindValue($this->dialect->parameterToString($param), $nodeId);
		$stmt->execute();
    	$this->commitTransaction();
		$result = $stmt->fetchAssociative();

    	return new Data\Node($keyId, $nodeId, array_diff_key($result, array_flip(['_scope', '_parent', '_order'])), $result['_scope'], $result['_parent'], $result['_order']);
	}

	public function findNodeField(string $keyId, string $nodeId, string $fieldId) : Data\NodeField {
		$param = new Parameter('nodeId');
		$select = $this->queryBuilder->getSelectForFindNodeField($keyId, $fieldId, $param);

		$this->beginTransaction();
		$stmt = $this->connection->prepare($this->dialect->selectToString($select));
		$stmt->bindValue($this->dialect->parameterToString($param), $nodeId);
		$stmt->execute();
    	$this->commitTransaction();
		$result = $stmt->fetch();

		if($result === false) {
			throw new \Exception("not found");
		}

		return new Data\NodeField($keyId, $nodeId, $fieldId, $result);
	}

	public function findNodeChildren(string $keyId, string $nodeId, string $childKeyId) : Data\NodeCollection {
		$this->beginTransaction();

		if($keyId === $childKeyId) {
			$self = $this->findNode($keyId, $nodeId);
			$scope = $self->getScope();
			$parent = $nodeId;
		} else {
			$scope = $nodeId;
			$parent = null;
		}

		$scopeParam = new Parameter('_scope');
		$parentParam = new Parameter('_parent');

		$select = $this->queryBuilder->getSelectForFindNodes($childKeyId, $scopeParam, $parentParam);
		$stmt = $this->connection->prepare($this->dialect->selectToString($select));
		$stmt->bindValue($this->dialect->parameterToString($scopeParam), $scope);
		$stmt->bindValue($this->dialect->parameterToString($parentParam), $parent);
		$stmt->execute();    	
		$rows = $stmt->fetchAllAssociativeIndexed();
    	$this->commitTransaction();

		return new Data\NodeCollection(
			$childKeyId,
			$rows,
			$scope,
			$parent
		);
	}

	public function findNodeAllChildren(string $keyId, string $nodeId) : Data\MultiCollection {
		$groupedRows = [];
		$self = $this->findNode($keyId, $nodeId);

		$scopeParam = new Parameter('scope');
		$parentParam = new Parameter('child');

		foreach ($this->schemaDef->getKeyIdsScopedInsideAndReflexiveSelf($keyId) as $childKeyId) {
			$select = $this->queryBuilder->getSelectForFindNodes($childKeyId, $scopeParam, $parentParam);
			$stmt = $this->connection->prepare($this->dialect->selectToString($select));
			
			if($childKeyId == $keyId) {
				$stmt->bindValue($this->dialect->parameterToString($scopeParam), $self->getScope());
				$stmt->bindValue($this->dialect->parameterToString($parentParam), $self->getId());
			} else {
				$stmt->bindValue($this->dialect->parameterToString($scopeParam), $nodeId);
				$stmt->bindValue($this->dialect->parameterToString($parentParam), null);
			}

			$stmt->execute();
			$groupedRows[$childKeyId] = $stmt->fetchAllAssociativeIndexed();
		}

		return new Data\MultiCollection(
			$keyId, 
			$nodeId, 
			$groupedRows, 
			$self->getScope(),
			$self->getParent()
		);
	}

	public function findNodeDirectParent(string $keyId, string $nodeId) : ?Data\Node {
		$self = $this->findNode($keyId, $nodeId);

		if(!empty($self->hasParent())) {
			return $this->findNode($keyId, $self->getParent());
		} else if($self->hasScope()) {
			return $this->findNode($this->schemaDef->getKeyScopeId($keyId), $self->getScope());
		} else {
			return null;
		}
	}

	public function findParentNodes(string $keyId, string $nodeId, ?int $limit = NULL) : Data\MultiCollection {
		$this->beginTransaction();
		$groupedNodes = [];
		$idParam = new Parameter('_id');


		$currentKey = $keyId;
		$currentId = $nodeId;
		while($currentKey && $currentId && $currentNode = $this->findNode($currentKey, $currentId)) {
			if(!$this->schemaDef->isKeyReflexive($keyId)) {
				$groupedNodes[$keyId] = $currentNode;
			} else {
				$select = $this->queryBuilder->getSelectForFindReflexiveParentNodes($currentKey, $idParam);
				$stmt = $this->connection->prepare($this->dialect->selectToString($select));
				$stmt->bindValue($this->dialect->parameterToString($idParam), $currentId);
				$stmt->execute();
				$groupedNodes[$currentKey] = $stmt->fetchAllAssociativeIndexed();
			}
			
			$currentKey = $this->schemaDef->getKeyScopeId($currentKey);
			$currentId = $currentNode->getScope();
		}

		return new Data\MultiCollection(null,null,$groupedNodes,null,null);
	}

	public function findNodeMoveTargets(string $keyId, string $nodeId) {
		$groupedRows = [];

		$rootKeyIds = [];
		if($this->schemaDef->isKeyReflexive($keyId)) {
			$idParam = new Parameter('_id');
			$select = $this->queryBuilder->getSelectForFindHierarchyCousins($keyId, $idParam);

			$this->beginTransaction();
			$stmt = $this->connection->prepare($this->dialect->selectToString($select));
			$stmt->bindValue($this->dialect->parameterToString($idParam), $nodeId);
			$stmt->execute();
	    	$this->commitTransaction();

			$groupedRows[$keyId] = $stmt->fetchAll(\PDO::FETCH_GROUP);
			$rootKeyIds[] = $keyId;
		}

		if($this->schemaDef->isKeyScoped($keyId)) {
			$scope = $this->schemaDef->getKeyScopeId($keyId);

			$select = $this->queryBuilder->getSelectForFindHierarchy($scope, null, null);

			$this->beginTransaction();
			$stmt = $this->connection->prepare($this->dialect->selectToString($select));
			$stmt->execute();
	    	$this->commitTransaction();

			$groupedRows[$scope] = $stmt->fetchAll(\PDO::FETCH_GROUP);
			$rootKeyIds[] = $scope;
		}

		return new Data\MultiTree(
			$rootKeyIds,
			$groupedRows
		);
	}

	public function findNodeSiblings(string $keyId, string $nodeId) {
		$directParent = $this->findNodeDirectParent($keyId, $nodeId);

		$self = $this->findNode($keyId, $nodeId);

		if(!empty($self->hasParent())) {
			return $this->findNodeChildren($keyId, $self->getParent(), $keyId);
		} else if($self->hasScope()) {
			return $this->findNodeChildren($this->schemaDef->getKeyScopeId($keyId), $self->getScope(), $keyId);
		} else {
			return $this->findRootNodes($keyId);
		}
	}

	public function findAllDefects() {
		return array_map(fn($key) => $this->findDefectsForKeyInternal($key), $this->queryBuilder->getDiagnosableKeys());
	}

	public function findDefectsForKey(string $keyId) {
		$this->beginTransaction();
		$result = $this->findDefectsForKeyInternal($keyId);
    	$this->commitTransaction();

    	return $result;
	}

	private function findDefectsForKeyInternal(string $keyId) {
		$rows = [];
		$columns = [];
		foreach($this->queryBuilder->getDiagnosisQueriesForKey($keyId) AS $name => $select) {
			$stmt = $this->connection->prepare($this->dialect->selectToString($select));
			$stmt->execute();
			$rows[$name] = $stmt->fetchAll();
			$columns[$name] = $this->extractColumnNamesFromSelect($select);
		}

    	return new Data\Diagnostic($keyId, $rows, $columns);
	}

	private function extractColumnNamesFromSelect($select) {
		$projections = $select->getProjections();
		return array_map(fn($proj, $i) => $proj->getAutoName($i)->getString(), $projections, array_keys($projections));
	}

	private function beginTransaction() {
		if($this->transactionDepth++ === 0) {
			$this->connection->beginTransaction();
		}
	}

	private function commitTransaction() {
		if(--$this->transactionDepth === 0) {
			$this->connection->commit();
		}
	}
}