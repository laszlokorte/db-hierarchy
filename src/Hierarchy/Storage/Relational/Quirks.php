<?php

namespace App\Hierarchy\Storage\Relational;

use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Schema\Definition\ColumnDefinition;
use App\Hierarchy\Storage\Relational\Algebra\Identifier;


class Quirks {
	public function __construct(private bool $noDeferredForeignKeys) {

	}

	public function noDeferredFK() {
		return $this->noDeferredForeignKeys;
	}
}
