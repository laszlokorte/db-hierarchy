<?php

namespace App\Hierarchy\Schema;

use App\Hierarchy\Schema\Definition\SchemaDefinition;

class HierarchyNormalizer {
	public function __construct(
		private SchemaDefinition $def, 
		private string $keyId
	) {
	}

	public function getViewName() {
		return sprintf('%s_hierarchy', $this->def->getKeyReflexivityTable($this->keyId));
	}

	public function getSelectStatement() {
		
	}
}