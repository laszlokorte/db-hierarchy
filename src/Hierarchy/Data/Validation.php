<?php

namespace App\Hierarchy\Data;

class Validation {

	public function __construct(
		private string $keyId, 
		private ?string $nodeId, 
		private mixed $fieldData,
		private array $fieldErrors, 
		private ?string $scopeId = NULL, 
		private ?string $parentId = NULL
	) {
	}

	public function getKeyId() {
		return $this->keyId;
	}

	public function getNodeId() {
		return $this->nodeId;
	}

	public function isValid() {
		return empty($this->fieldErrors);
	}

	public function getAllErrors() {
		return array_merge([], ...$this->fieldErrors);
	}

	public function isFieldValid($fieldId) {
		return empty($this->fieldErrors[$fieldId]);
	}

	public function getErrorFields() {
		return array_keys($this->fieldErrors);
	}

	public function getFieldErrors($fieldId) {
		return $this->fieldErrors[$fieldId];
	}
}