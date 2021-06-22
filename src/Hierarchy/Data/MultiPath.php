<?php

namespace App\Hierarchy\Data;

class MultiPath {
	public function __construct(private array $nodePaths) {

	}

	public function getSegmentsBottomUp() {
		return $this->nodePaths;
	}

	public function getSegmentsTopDown() {
		return array_reverse($this->nodePaths);
	}

	public function isEmpty() {
		return empty($this->nodePaths);
	}

	public function lastKey() {
		return $this->lastSegment()->getKey() ?? null;
	}

	public function lastId() {
		return $this->lastSegment()->lastId() ?? null;
	}

	public function lastSegment() {
		return $this->nodePaths[count($this->nodePaths) - 1]??null;
	}
}