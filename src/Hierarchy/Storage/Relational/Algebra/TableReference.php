<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

class TableReference {
	public function __construct(
		private Identifier $table,
		private ?Identifier $alias = null
	) {
	}

	public function getName() {
		return $this->table;
	}

	public function getAlias() {
		return $this->alias;
	}

	public function getUsageName() {
		return $this->alias ?: $this->name;
	}
}