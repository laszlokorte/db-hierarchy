<?php

namespace App\Hierarchy\Storage\Relational;

use App\Hierarchy\Storage\StorageReaderInterface;
use App\Hierarchy\Data\NodeCollection;
use App\Hierarchy\Data\KeyCollection;
use App\Hierarchy\Data\Node;
use App\Hierarchy\Data\Tree;
use App\Hierarchy\Data\Field;
use App\Hierarchy\Data\NodePath;

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

class RelationalReader implements StorageReaderInterface {
	public function __construct(private SchemaRoot $schema, private RelationalSchemaNaming $naming) {
		
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
