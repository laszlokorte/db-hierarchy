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

	public function findNodes(string $keyId, string $nodeId) : NodeCollection {
		
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

	public function findNodeField(string $keyId, string $nodeId, string $fieldId) : ?Field {
		
	}

	public function findNodeChildren(string $keyId, string $nodeId, string $childKeyId) : NodeCollection {
		
	}

	public function findNodeDirectParent(string $keyId, string $nodeId) : ?Node {
		
	}

	public function findNodeReflexiveParents(string $keyId, string $nodeId, ?int $limit = NULL) : NodePath {
		
	}

	public function findNodeParents(string $keyId, string $nodeId, ?int $limit = NULL) : NodePath {
		return new NodePath();
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