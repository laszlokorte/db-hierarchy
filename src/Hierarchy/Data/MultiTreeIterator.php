<?php

namespace App\Hierarchy\Data;

use RecursiveIterator;

class MultiTreeIterator implements RecursiveIterator {

	private MultiTree $tree;

	private $keyId;
	private $scopeId;
	private $parentId;
	private $depth;

	private $nodes;

	public function __construct(MultiTree $tree, $keyId, $scopeId, $parentId, $depth) {
		$this->tree = $tree;
		$this->keyId = $keyId;
		$this->scopeId = $scopeId;
		$this->parentId = $parentId;
		$this->depth = $depth;
	}

	public function getChildren() : ?RecursiveIterator {
		return new MultiTreeScopeIterator(
			$this->tree,
			$this->keyId,
			current($this->nodes),
			$this->depth+1
		);
	}

	public function hasChildren(): bool {
		return $this->tree->hasNodes($this->keyId, $this->scopeId, $this->parentId);
	}

	public function current() : mixed {
		return (object)[
			'node' => current($this->nodes),
			'depth' => $this->depth,
		];
	}

	public function key() : mixed {
		return $this->i;
	}

	public function next() : void {
		array_shift($this->nodes);
		$this->i++;
	}

	public function rewind() : void {
		$this->i = 0;
		$this->nodes = $this->tree->getNodes($this->keyId, $this->scopeId, $this->parentId);
	}

	public function valid() : bool {
		return !empty($this->nodes);
	}

}