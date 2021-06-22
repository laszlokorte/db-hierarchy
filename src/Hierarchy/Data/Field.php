<?php

namespace App\Hierarchy\Data;

class Field {
	public function __construct(private string $keyId, private string $nodeId, private string $fieldId, private mixed $value) {

	}

	public function toArray() {
		return [
    		'key' => $this->keyId,
    		'id' => $this->nodeId,
    		'field' => $this->fieldId,
    		'value' => $this->value,
    	];
	}
}