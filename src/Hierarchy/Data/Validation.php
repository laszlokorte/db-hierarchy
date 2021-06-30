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


}