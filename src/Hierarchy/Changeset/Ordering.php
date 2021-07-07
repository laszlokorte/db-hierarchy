<?php

namespace App\Hierarchy\Changeset;

class Ordering {
	public function __construct(private $keyId, private string $nodeId, private int $targetOrder, private ?array $errors) {
		
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