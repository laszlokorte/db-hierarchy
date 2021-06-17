<?php

namespace App\Hierarchy\Schema\Definition;

class ReflexivityDefinition {
	public function __construct(
		private $parentColumn = 'parent_id', 
		private $childColumn = 'child_id',
	) {
	}

	public function getParentColumnName() {
		return $this->parentColumn;
	}

	public function getChildColumnName() {
		return $this->childColumn;
	}

	public function deriveTableName($baseTableName) {
		return sprintf('%s_closure', $baseTableName);
	}
}