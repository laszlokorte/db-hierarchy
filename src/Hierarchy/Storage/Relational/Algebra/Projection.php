<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;

use App\Hierarchy\Storage\Relational\Algebra\Value\ColumnReference;

class Projection {
	public function __construct(
		private ValueInterface $value,
		private ?Identifier $alias = NULL
	) {
	}

	public function getValue() {
		return $this->value;
	}

	public function getAlias() {
		return $this->alias;
	}

	public function getAutoName(string $fallback) {
		if($this->alias !== null) {
			return $this->alias;
		}

		if($this->value instanceof ColumnReference) {
			return $this->value->getName();
		}

		return new Identifier($fallback);
	}
}