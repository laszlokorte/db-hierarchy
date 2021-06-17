<?php

namespace App\Hierarchy\Schema;

use App\Hierarchy\Schema\Definition\SchemaDefinition;

class Diagnosis {
	public function __construct(
		private SchemaDefinition $def
	) {
	}

}