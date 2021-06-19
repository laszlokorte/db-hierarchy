<?php

namespace App\Hierarchy\Storage;

interface StorageWriterInterface {
	public function createNode(string $keyId, $scopeId, $parentId);
	public function updateNode(string $keyId);
	public function deleteNode(string $keyId, $nodeId);
	public function moveNode(string $keyId, $nodeId, $targetScopeId, $targetParentId);
}