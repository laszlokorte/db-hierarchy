<?php

namespace App\Hierarchy\Schema;

use App\Hierarchy\Schema\Definition\SchemaDefinition;

class ClosureInvalidNormalizer {
	public function __construct(
		private SchemaDefinition $def, 
		private string $keyId
	) {
	}

	public function getViewName() {
		return sprintf('%s_invalid', $this->def->getKeyReflexivityTable($this->keyId));
	}

	public function getSelectStatement() {
		
	}
}