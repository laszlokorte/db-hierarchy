<?php

namespace App\Hierarchy\Changeset;

class Creation {
	public function __construct(private $keyId, private ?string $scopeId, private ?string $parentId, private array $columnData, private ?array $fieldErrors = null, private ?array $scopeErrors = null, private ?array $parentErrors = null) {

	}

	public function getKeyId() {
		return $this->keyId;
	}

	public function hasBeenValidated() {
		return $this->fieldErrors !== null;
	}

	public function isValid() {
		return empty($this->fieldErrors) && empty($this->scopeErrors) && empty($this->parentErrors);
	}

	public function fieldHasErrors(string $fieldId) {
		return !empty($this->fieldErrors[$fieldId]);
	}

	public function getFieldErrors(string $fieldId) {
		return $this->fieldErrors[$fieldId];
	}

	public function hasScopeErrors() {
		return !empty($this->scopeErrors);
	}

	public function getScopeErrors() {
		return $this->scopeErrors;
	}

	public function hasParentErrors() {
		return !empty($this->parentErrors);
	}

	public function getParentErrors() {
		return $this->parentErrors;
	}

	public function getAllErrors() {
		return array_merge(
			$this->fieldErrors, 
			['_scope' => $this->scopeErrors, '_parent' => $this->parentErrors]
		);
	}
}