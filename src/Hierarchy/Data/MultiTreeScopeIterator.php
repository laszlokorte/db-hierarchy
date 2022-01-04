<?php

namespace App\Hierarchy\Data;

use RecursiveIterator;

class MultiTreeScopeIterator implements RecursiveIterator {

	public function __construct($tree, $keyId, $node, $depth) {
		$this->tree = $tree;
		$this->keyId = $keyId;
		$this->node = $node;
		$this->depth = $depth;
	}

	public function getChildren() {
		$rootKey = current($this->rootKeys);
		
		if($this->keyId == $rootKey) {
			$scopeId = $this->node->getScope();
			$parentId = $this->node->getId();
		} else {
			$scopeId = $this->node->getScope();
			$parentId = null;
		}

		return new MultiTreeIterator($this->tree, $rootKey, $scopeId, $parentId, $this->depth+1);
	}

	public function hasChildren(): bool {
		$rootKey = current($this->rootKeys);
		if($this->keyId == $rootKey) {
			$scopeId = $this->node->getScope();
			$parentId = $this->node->getId();
		} else {
			$scopeId = $this->node->getScope();
			$parentId = null;
			
		}
		return $this->tree->hasNodes($rootKey, $scopeId, $parentId);
	}

	public function current() : mixed {
		return current($this->rootKeys);
	}

	public function key() : mixed {
		return null;
	}

	public function next() : void {
		$this->i++;
		array_shift($this->rootKeys);
	}

	public function rewind() : void {
		$this->i = 0;
		$this->rootKeys = $this->tree->getRootKeys();
	}

	public function valid() : bool {
		return !empty($this->rootKeys);
	}

}