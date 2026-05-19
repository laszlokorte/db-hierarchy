<?php

namespace App\Hierarchy\Data;

use App\Hierarchy\Schema\Key;

use RecursiveIterator;

class MultiTreeOuterIterator implements RecursiveIterator {

	private MultiTree $tree;

	private $key;
	private $depth;

	private $scopes;

	public function __construct(MultiTree $tree, Key $key, $depth) {
		$this->tree = $tree;
		$this->key = $key;
		$this->depth = $depth;
	}

	public function getChildren() : ?RecursiveIterator {
		return new MultiTreeIterator(
			$this->tree,
			$this->key,
			current($this->scopes),
			null,
			$this->depth+1
		);
	}

	public function hasChildren(): bool {
		return $this->tree->hasNodes($this->key->getId(), current($this->scopes), null);
	}

	public function current() : mixed {
		return current($this->scopes);
	}

	public function key() : mixed {
		$currentNode = current($this->scopes);
		
		return key($this->scopes);
	}

	public function next() : void {
		array_shift($this->scopes);
		$this->i++;
	}

	public function rewind() : void {
		$this->i = 0;
		$this->scopes = $this->tree->getScopes($this->key->getId());
	}

	public function valid() : bool {
		return !empty($this->scopes);
	}

}