<?php

namespace App\Hierarchy\Changeset;

class Movement {
	public function __construct(private $keyId, private string $nodeId, private string $targetScope, private string $targetParent, private ?array $errors) {
		
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