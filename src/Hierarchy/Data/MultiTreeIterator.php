<?php

namespace App\Hierarchy\Data;

use App\Hierarchy\Schema\Key;

use RecursiveIterator;

class MultiTreeIterator implements RecursiveIterator {

	private MultiTree $tree;

	private $key;
	private $scopeId;
	private $parentId;
	private $depth;

	private $nodes;

	public function __construct(MultiTree $tree, Key $key, $scopeId, $parentId, $depth) {
		$this->tree = $tree;
		$this->key = $key;
		$this->scopeId = $scopeId;
		$this->parentId = $parentId;
		$this->depth = $depth;
	}

	public function getChildren() : ?RecursiveIterator {
		return new MultiTreeScopeIterator(
			$this->tree,
			$this->key,
			current($this->nodes),
			$this->depth+1
		);
	}

	public function hasChildren(): bool {
		return $this->tree->hasNodes($this->key->getId(), $this->scopeId, $this->parentId);
	}

	public function current() : mixed {
		return current($this->nodes);
	}

	public function key() : mixed {
		$currentNode = current($this->nodes);
		
		return sprintf('%s/%s', $currentNode->getKey(), $currentNode->getId());
	}

	public function next() : void {
		array_shift($this->nodes);
		$this->i++;
	}

	public function rewind() : void {
		$this->i = 0;
		$this->nodes = $this->tree->getNodes($this->key->getId(), $this->scopeId, $this->parentId);
	}

	public function valid() : bool {
		return !empty($this->nodes);
	}

}