<?php

namespace App\Hierarchy\Storage\Relational;


use App\Hierarchy\Schema\Definition\SchemaDefinition;

class ValidationBuilder {

	public function __construct(private SchemaDefinition $schemaDef, private Naming $naming) {
		
	}

}
