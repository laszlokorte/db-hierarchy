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

	public function fieldHasErrors(string $fieldId) {
		return !empty($this->errors[$fieldId]);
	}

	public function getFieldErrors(string $fieldId) {
		return $this->errors[$fieldId];
	}

	public function getAllErrors() {
		return $this->errors;
	}

	public function getColumnValue($columnName) {
		return $this->columnData[$columnName] ?? $this->previousData[$columnName] ?? null;
	}
}