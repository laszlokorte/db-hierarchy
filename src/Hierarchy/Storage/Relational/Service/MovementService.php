<?php

namespace App\Hierarchy\Storage\Relational\Service;

use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Storage\Relational\Dialect\DialectInterface;
use App\Hierarchy\Storage\Relational\ColumnCoder;

use Doctrine\DBAL\Connection;

class MovementService {
	public function __construct(private SchemaDefinition $schemaDef, private Connection $connection, private DialectInterface $dialect, private ColumnCoder $coder) {

	}
}