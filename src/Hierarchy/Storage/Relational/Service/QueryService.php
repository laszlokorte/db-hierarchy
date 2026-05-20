<?php

namespace App\Hierarchy\Storage\Relational\Service;

use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Storage\Relational\Dialect\DialectInterface;
use App\Hierarchy\Storage\Relational\ColumnCoder;
use App\Hierarchy\Storage\Relational\Algebra\Value\Parameter;
use App\Hierarchy\Storage\Relational\Algebra\Value\Constant;
use App\Hierarchy\Data;

use App\Util\ResultFetcher;

use Doctrine\DBAL\Connection;

class QueryService {
	public function __construct(private SchemaDefinition $schemaDef, private QueryCommandBuilder $commandBuilder, private Connection $connection, private DialectInterface $dialect, private ColumnCoder $coder) {

	}

	public function findAllNodes(string $keyId, bool $deep = false) : Data\NodeCollection {
		$select = $this->commandBuilder->getSelectForFindNodes($keyId, null, $deep ? null : new Constant(null));

		$stmt = $this->connection->prepare($this->dialect->selectToString($select));
		$stmtResult = $stmt->executeQuery();

		$rows = $stmtResult->fetchAllAssociativeIndexed();

		return new Data\NodeCollection(
			$keyId,
			$rows,
			NULL,
			NULL
		);
	}

	public function findAllRootNodes() : Data\MultiCollection {
		$groupedRows = [];

		foreach ($this->schemaDef->getRootScopeKeyIds() as $keyId) {
			$select = $this->commandBuilder->getSelectForFindNodes($keyId, new Constant(null), new Constant(null));
			$stmt = $this->connection->prepare($this->dialect->selectToString($select));
			$stmtResult = $stmt->executeQuery();
			$groupedRows[$keyId] = $stmtResult->fetchAllAssociativeIndexed();
		}

		return new Data\MultiCollection(
			null,
			null,
			$groupedRows,
			null,
			null
		);
	}

	public function findHierarchyNodes($keyId) : Data\NodeTree {
		$select = $this->commandBuilder->getSelectForFindHierarchy($keyId, null, null);

		$stmt = $this->connection->prepare($this->dialect->selectToString($select));
		$stmtResult = $stmt->executeQuery();

		$rows = ResultFetcher::fetchGrouped($stmtResult);

		return new Data\NodeTree(
			$keyId,
			$rows,
			NULL,
			NULL
		);
	}

	public function findAllHierarchyNodes() : Data\MultiTree {
		$groupedRows = [];

		foreach ($this->schemaDef->getAllKeyIds() as $keyId) {
			$select = $this->commandBuilder->getSelectForFindHierarchy($keyId, null, null);
			$stmt = $this->connection->prepare($this->dialect->selectToString($select));
			$stmtResult = $stmt->executeQuery();
			$groupedRows[$keyId] = ResultFetcher::fetchGrouped($stmtResult);
		}

		return new Data\MultiTree(
			$groupedRows
		);
	}

	public function findNode(string $keyId, string $nodeId) : ?Data\Node {
		$param = new Parameter('nodeId');
		$select = $this->commandBuilder->getSelectForFindNode($keyId, $param);

		$stmt = $this->connection->prepare($this->dialect->selectToString($select));
		$stmt->bindValue($this->dialect->parameterToString($param), $nodeId);
		$stmtResult = $stmt->executeQuery();

		$result = $stmtResult->fetchAssociative();

		if(!$result) {
			throw new \Exception('node not found');
		}

    	return new Data\Node($keyId, $nodeId, array_diff_key($result, array_flip(['_scope', '_parent', '_order'])), $result['_scope'], $result['_parent'], $result['_order']);
	}

	public function findNodeField(string $keyId, string $nodeId, string $fieldId) : Data\NodeField {
		$param = new Parameter('nodeId');
		$select = $this->commandBuilder->getSelectForFindNodeField($keyId, $fieldId, $param);

		$stmt = $this->connection->prepare($this->dialect->selectToString($select));
		$stmt->bindValue($this->dialect->parameterToString($param), $nodeId);
		$stmtResult = $stmt->executeQuery();

		$result = $stmtResult->fetchAssociative();

		if($result === false) {
			throw new \Exception("not found");
		}

		return new Data\NodeField($keyId, $nodeId, $fieldId, $result);
	}

	public function findNodeChildren(string $keyId, string $nodeId, string $childKeyId) : Data\NodeCollection {
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

		$select = $this->commandBuilder->getSelectForFindNodes($childKeyId, $scopeParam, $parentParam);
		$stmt = $this->connection->prepare($this->dialect->selectToString($select));
		$stmt->bindValue($this->dialect->parameterToString($scopeParam), $scope);
		$stmt->bindValue($this->dialect->parameterToString($parentParam), $parent);
		$stmtResult = $stmt->executeQuery();
		$rows = $stmtResult->fetchAllAssociativeIndexed();


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
			$select = $this->commandBuilder->getSelectForFindNodes($childKeyId, $scopeParam, $parentParam);
			$stmt = $this->connection->prepare($this->dialect->selectToString($select));

			if($childKeyId == $keyId) {
				$stmt->bindValue($this->dialect->parameterToString($scopeParam), $self->getScope());
				$stmt->bindValue($this->dialect->parameterToString($parentParam), $self->getId());
			} else {
				$stmt->bindValue($this->dialect->parameterToString($scopeParam), $nodeId);
				$stmt->bindValue($this->dialect->parameterToString($parentParam), null);
			}

			$stmtResult = $stmt->executeQuery();
			$groupedRows[$childKeyId] = $stmtResult->fetchAllAssociativeIndexed();
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
		$groupedNodes = [];
		$idParam = new Parameter('_id');

		$currentKey = $keyId;
		$currentId = $nodeId;
		while($currentKey && $currentId) {
			if(!$this->schemaDef->isKeyReflexive($currentKey)) {
				$select = $this->commandBuilder->getSelectForFindNode($currentKey, $idParam);
				$stmt = $this->connection->prepare($this->dialect->selectToString($select));
				$stmt->bindValue($this->dialect->parameterToString($idParam), $currentId);
				$stmtResult = $stmt->executeQuery();
				$groupedNodes[$currentKey] = $stmtResult->fetchAllAssociativeIndexed();
			} else {
				$select = $this->commandBuilder->getSelectForFindReflexiveParentNodes($currentKey, $idParam);
				$stmt = $this->connection->prepare($this->dialect->selectToString($select));
				$stmt->bindValue($this->dialect->parameterToString($idParam), $currentId);
				$stmtResult = $stmt->executeQuery();
				$groupedNodes[$currentKey] = $stmtResult->fetchAllAssociativeIndexed();
			}

			$currentNode = end($groupedNodes[$currentKey]);
			$currentKey = $this->schemaDef->getKeyScopeId($currentKey);
			$currentId = $currentNode['_scope'];
		}

		return new Data\MultiCollection(null,null,$groupedNodes,null,null);
	}


	public function findNodeSiblings(string $keyId, string $nodeId) {
		$self = $this->findNode($keyId, $nodeId);

		if(!empty($self->hasParent())) {
			return $this->findNodeChildren($keyId, $self->getParent(), $keyId);
		} else if($self->hasScope()) {
			return $this->findNodeChildren($this->schemaDef->getKeyScopeId($keyId), $self->getScope(), $keyId);
		} else {
			return $this->findAllNodes($keyId);
		}
	}
}
