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

class QueryBuilder {
	public function __construct(private SchemaRoot $schema, private Naming $naming) {
		
	}

	public function getSelectForfindNodes(string $keyId) {
		
	}

	public function getSelectForfindNode(string $keyId) {
		
	}

	public function getSelectForfindNodeField(string $keyId, string $fieldId) {
		
	}

	public function getSelectForfindNodeChildren(string $keyId, string $childKeyId) {
		
	}

	public function getSelectForfindNodeDirectParent(string $keyId) {
		
	}

	public function getSelectForfindNodeReflexiveParents(string $keyId, ?int $limit = NULL) {
		
	}

	public function getSelectForfindNodeParents(string $keyId, ?int $limit = NULL) {
		
	}

	public function getSelectForfindAllDefects() {

	}

	public function getSelectForfindDefectsForKey(string $keyId) {

	}

	public function getSelectForfindDefectsForNode(string $keyId) {

	}
}
