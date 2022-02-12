<?php

namespace App\Hierarchy\Data;

use App\Hierarchy\Schema\Key;

use Iterator;

class NodeCollectionIterator implements Iterator {
	private $collection;
	private $ids;
	private $i;

	public function __construct(NodeCollection $collection) {
		$this->collection = $collection;
		$this->ids = $collection->getIds();
		$this->i = 0;
	}

	public function current() : mixed {
		return $this->collection->getNode($this->ids[$this->i]);
	}

	public function key() : mixed {
		return $this->i;
	}

	public function next() : void {
		$this->i++;
	}

	public function rewind() : void {
		$this->ids = $this->collection->getIds();
		$this->i = 0;
	}

	public function valid() : bool {
		return $this->i < count($this->ids);
	}
}