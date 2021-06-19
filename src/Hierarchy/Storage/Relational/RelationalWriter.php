<?php

namespace App\Hierarchy\Storage\Relational;

use App\Hierarchy\Storage\StorageWriterInterface;

class RelationalWriter implements StorageWriterInterface {

	public function __construct(private SchemaRoot $schema, private RelationalSchemaNaming $naming) {
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