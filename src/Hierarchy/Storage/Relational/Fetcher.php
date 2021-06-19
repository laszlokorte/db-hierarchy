<?php

namespace App\Hierarchy\Storage\Relational;

use App\Hierarchy\Storage\Relational\Dialect\DialectInterface;

use Doctrine\DBAL\Connection;

class Fetcher {
	public function __construct(QueryBuilder $commandBuilder, Connection $connection, DialectInterface $dialect) {

	}

	public function findNodes(string $keyId, string $nodeId) : NodeCollection {
		
	}

	public function findNode(string $keyId, string $nodeId) : ?Node {
		
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
		
	}

	public function findAllDefects() {

	}

	public function findDefectsForKey(string $keyId) {

	}

	public function findDefectsForNode(string $keyId, string $nodeId) {

	}
}