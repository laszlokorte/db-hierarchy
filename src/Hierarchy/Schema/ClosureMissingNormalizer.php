<?php

namespace App\Hierarchy\Schema;

use App\Hierarchy\Schema\Definition\SchemaDefinition;

class ClosureMissingNormalizer {
	public function __construct(
		private SchemaDefinition $def, 
		private string $keyId
	) {
	}

	public function getViewName() {
		return sprintf('%s_missing', $this->def->getKeyReflexivityTable($this->keyId));
	}
}