<?php

namespace App\Hierarchy\Changeset;

class Update {
	public function __construct(private $keyId, private string $nodeId, private array $columnData, private array $previousData, private ?array $errors) {
		
	}

	public function getKeyId() {
		return $this->keyId;
	}

	public function getNodeId() {
		return $this->nodeId;
	}

	public function hasBeenValidated() {
		return $this->errors !== null;
	}

	public function isValid() {
		return empty($this->errors);
	}
}