<?php

namespace App\Hierarchy\Storage\Relational;

class RelationalWriter {
	public function __construct(private SchemaRoot $schema) {
		
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