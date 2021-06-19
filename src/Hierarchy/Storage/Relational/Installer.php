<?php

namespace App\Hierarchy\Storage\Relational;

use App\Hierarchy\Storage\Relational\Dialect\DialectInterface;

use Doctrine\DBAL\Connection;

class Installer {
	public function __construct(SchemaBuilder $commandBuilder, Connection $connection, DialectInterface $dialect) {
	}

	public function getSchemaDeclerations() {
		$schema = '';

		foreach ($relSchema->getAllTables() as $t) {
			$schema .= PHP_EOL . $sqlite->createTableToString($t);
		}
		foreach ($relSchema->getAllViews() as $v) {
			$schema .= PHP_EOL . $sqlite->createViewToString($v);
		}

		return $schema;
	}

	public function createSchema() {
		$this->connection->beginTransaction();
		foreach ($relSchema->getAllTables() as $t) {
			$this->connection->executeStatement($sqlite->createTableToString($t));
		}
		foreach ($relSchema->getAllViews() as $v) {
			$this->connection->executeStatement($sqlite->createViewToString($v));
		}
    	$this->connection->commit();
	}

	public function dropSchema() {
		$this->connection->beginTransaction();
    	foreach ($relSchema->getAllViews() as $v) {
    		$this->connection->executeStatement($sqlite->dropViewToString($v));
    	}
		foreach ($relSchema->getAllTables() as $t) {
			$this->connection->executeStatement($sqlite->dropTableToString($t));
		}
    	$this->connection->commit();
	}
}