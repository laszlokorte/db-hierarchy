<?php

namespace App\Hierarchy\Storage\Relational\Exception;

use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Schema\Definition\ColumnDefinition;
use App\Hierarchy\Storage\Relational\Algebra\Identifier;


class DeletionBlockedException extends \Exception {
	public function __construct() {

	}
}
