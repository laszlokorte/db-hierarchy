<?php

namespace App\Hierarchy\Storage\Relational;

use App\Hierarchy\Storage\Relational\Dialect\DialectInterface;

use Doctrine\DBAL\Connection;

class Commander {
	public function __construct(CommandBuilder $commandBuilder, Connection $connection, DialectInterface $dialect) {

	}

	public function createNode(string $keyId, $scopeId, $parentId) {
		
	}

	public function updateNode(string $keyId) {
		
	}

	public function deleteNode(string $keyId, $nodeId) {
		
	}

	public function moveNode(string $keyId, $nodeId, $targetScopeId, $targetParentId) {
		
	}
}