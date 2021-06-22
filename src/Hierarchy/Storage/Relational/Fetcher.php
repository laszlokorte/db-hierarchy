<?php

namespace App\Hierarchy\Storage\Relational;

use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Storage\Relational\Dialect\DialectInterface;
use App\Hierarchy\Storage\Relational\Algebra\Value\Parameter;
use App\Hierarchy\Data;

use Doctrine\DBAL\Connection;

class Fetcher {
	public function __construct(private SchemaDefinition $schemaDef, private QueryBuilder $queryBuilder, private Connection $connection, private DialectInterface $dialect) {

	}

	public function findRootNodes(string $keyId) : Data\NodeCollection {
		$select = $this->queryBuilder->getSelectForFindRootNodes($keyId);

		$this->connection->beginTransaction();
		$stmt = $this->connection->prepare($this->dialect->selectToString($select));
		$stmt->execute();
    	$this->connection->commit();
		$rows = $stmt->fetchAllAssociativeIndexed();

		return new Data\NodeCollection(
			$keyId,
			$rows,
			NULL,
			NULL,
			[]
		);
	}

	public function findAllRootNodes() : Data\MultiCollection {
		$groupedRows = [];
		$scopeId = null;
		$parentId = null;

		return new Data\MultiCollection(
			null, 
			null, 
			$groupedRows, 
			null,
			null
		);
	}

	public function findHierarchyNodes($keyId) : Data\NodeTree {
		return new Data\NodeTree();
	}

	public function findAllHierarchyNodes() : Data\MultiTree {
		return new Data\MultiTree();
	}

	public function findNode(string $keyId, string $nodeId) : ?Data\Node {
		$param = new Parameter('nodeId');
		$select = $this->queryBuilder->getSelectForFindNode($keyId, $param);

		$this->connection->beginTransaction();
		$stmt = $this->connection->prepare($this->dialect->selectToString($select));
		$stmt->bindValue($this->dialect->parameterToString($param), $nodeId);
		$stmt->execute();
    	$this->connection->commit();
		$result = $stmt->fetchAssociative();

    	return new Data\Node($keyId, $nodeId, array_diff_key($result, array_flip(['_scope', '_parent', '_order'])), $result['_scope'], $result['_parent'], $result['_order']);
	}

	public function findNodeField(string $keyId, string $nodeId, string $fieldId) : Data\Field {
		return new Data\Field($keyId, $nodeId, $fieldId, []);
	}

	public function findNodeChildren(string $keyId, string $nodeId, string $childKeyId) : Data\NodeCollection {
		if($keyId === $childKeyId) {
			$self = $this->findNode($keyId, $nodeId);

			$rows = [];

			return new Data\NodeCollection(
				$keyId,
				$rows,
				$self->getScope(),
				$nodeId
			);
		} else {
			$rows = [];
			
			return new Data\NodeCollection(
				$childKeyId,
				$rows,
				$nodeId,
				null
			);
		}
	}

	public function findNodeAllChildren(string $keyId, string $nodeId) : Data\MultiCollection {
		$groupedRows = [];
		$scopeId = null;
		$parentId = null;

		return new Data\MultiCollection(
			$keyId, 
			$nodeId, 
			$groupedRows, 
			$scopeId,
			$parentId
		);
	}

	public function findNodeDirectParent(string $keyId, string $nodeId) : ?Node {
		$self = $this->findNode($keyId, $nodeId);

		if(!empty($self->hasParent())) {
			return $this->findNode($keyId, $self->getParent());
		} else if($self->hasScope()) {
			return $this->findNode($this->schemaDef->getKeyScopeId($keyId), $self->getScope());
		} else {
			return null;
		}
	}

	public function findNodeReflexiveParents(string $keyId, string $nodeId, ?int $limit = NULL) : Data\NodePath {
		if(!$this->schemaDef->isKeyReflexive($keyId)) {
			return new Data\NodePath($keyId, [$nodeId]);
		}

		$select = $this->queryBuilder->getSelectForFindNodeReflexiveParents($keyId);
		$stmt = $this->connection->prepare($this->dialect->selectToString($select));
		$stmt->bindValue($this->dialect->parameterToString(new Parameter('_id')), $nodeId);
		$stmt->execute();
		$ids = $stmt->fetchAll(\PDO::FETCH_COLUMN);

		return new Data\NodePath($keyId, $ids);
	}

	public function findNodeParents(string $keyId, string $nodeId, ?int $limit = NULL) : Data\MultiPath {
		$nodePaths = [];


		$currentKey = $keyId;
		$currentId = $nodeId;
		while($currentKey && $currentId && $currentNode = $this->findNode($currentKey, $currentId)) {
			$nodePaths[] = $this->findNodeReflexiveParents($keyId, $nodeId);
			$currentKey = $this->schemaDef->getKeyScopeId($currentKey);
			$currentId = $currentNode->getParent();
		}

		dump($nodePaths);

		return new Data\MultiPath($nodePaths);
	}

	public function findNodeMoveTargets(string $keyId, string $nodeId) {
		return new Data\MultiTree();
	}

	public function findAllDefects() {
		return array_map(fn($key) => $this->findDefectsForKeyInternal($key), $this->queryBuilder->getDiagnosableKeys());
	}

	public function findDefectsForKey(string $keyId) {
		$this->connection->beginTransaction();
		$result = $this->findDefectsForKeyInternal($keyId);
    	$this->connection->commit();

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
}