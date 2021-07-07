<?php

namespace App\Hierarchy\Changeset;

class Creation {
	public function __construct(private $keyId, private ?string $scopeId, private ?string $parentId, private array $columnData, private ?array $errors) {

	}

	public function getKeyId() {
		return $this->keyId;
	}

	public function hasBeenValidated() {
		return $this->errors !== null;
	}

	public function isValid() {
		return empty($this->errors);
	}
}