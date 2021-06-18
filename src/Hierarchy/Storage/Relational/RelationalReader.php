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

class RelationalReader {
	public function __construct(private SchemaRoot $schema) {
		
	}

	public function findNodes(string $keyId, string $nodeId) {
		
	}

	public function findNode(string $keyId, string $nodeId) {
		
	}

	public function findNodeField(string $keyId, string $nodeId, string $fieldId) {
		
	}

	public function findNodeChildren(string $keyId, string $nodeId, string $childKeyId) {
		
	}

	public function findNodeDirectParent(string $keyId, string $nodeId) {
		
	}

	public function findNodeReflexiveParents(string $keyId, string $nodeId, ?int $limit = NULL) {
		
	}

	public function findNodeParents(string $keyId, string $nodeId, ?int $limit = NULL) {
		
	}

	public function findAllDefects() {

	}

	public function findDefectsForKey(string $keyId) {

	}

	public function findDefectsForNode(string $keyId, string $nodeId) {

	}
}
