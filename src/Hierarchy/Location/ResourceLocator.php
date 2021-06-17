<?php

namespace App\Hierarchy\Location;

class ResourceLocator {
	public function __construct(
		private ?string $keyId = NULL,
		private ?string $nodeId = NULL,
		private ?string $childKeyId = NULL
	) {
	}

	public function isRoot() {
		return $this->keyId === NULL;
	}

	public function isCollection() {
		return $this->nodeId === NULL || $this->childKeyId !== NULL;
	}

	public function isNode() {
		return $this->nodeId === NULL || $this->childKeyId !== NULL;
	}

	public function isValid() {
		if($this->childKeyId !== NULL && $this->nodeId === NULL) {
			return false;
		}

		if($this->nodeId !== NULL && $this->keyId === NULL) {
			return false;
		}
	}
}