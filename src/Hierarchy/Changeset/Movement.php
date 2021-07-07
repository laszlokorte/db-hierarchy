<?php

namespace App\Hierarchy\Changeset;

class Movement {
	public function __construct(private $keyId, private string $nodeId, private ?string $targetScope, private ?string $targetParent, private ?array $errors) {
		
	}

	public function getKeyId() {
		return $this->keyId;
	}

	public function getNodeId() {
		return $this->nodeId;
	}

	public function getScope() {
		return $this->targetScope;
	}

	public function getParent() {
		return $this->targetParent;
	}

	public function hasBeenValidated() {
		return $this->errors !== null;
	}

	public function isValid() {
		return empty($this->errors);
	}

	public function getAllErrors() {
		return $this->errors;
	}
}