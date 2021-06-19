<?php

namespace App\Hierarchy\Storage\Relational;

use App\Hierarchy\Storage\Relational\Dialect\DialectInterface;

use Doctrine\DBAL\Connection;

class Installer {
	public function __construct(private SchemaBuilder $schmaBuilder, private Connection $connection, private DialectInterface $dialect) {
	}

	public function getSchemaDeclerations() {
		$schema = '';

		foreach ($this->schmaBuilder->getAllTables() as $t) {
			$schema .= PHP_EOL . $this->dialect->createTableToString($t);
		}
		foreach ($this->schmaBuilder->getAllViews() as $v) {
			$schema .= PHP_EOL . $this->dialect->createViewToString($v);
		}

		return $schema;
	}

	public function createSchema($dropOld) {
		$this->connection->beginTransaction();

		foreach ($this->schmaBuilder->getAllViews() as $v) {
    		$this->connection->executeStatement($this->dialect->dropViewToString($v));
    	}
		foreach ($this->schmaBuilder->getAllTables() as $t) {
			$this->connection->executeStatement($this->dialect->dropTableToString($t));
		}

		foreach ($this->schmaBuilder->getAllTables() as $t) {
			$this->connection->executeStatement($this->dialect->createTableToString($t));
		}
		foreach ($this->schmaBuilder->getAllViews() as $v) {
			$this->connection->executeStatement($this->dialect->createViewToString($v));
		}
    	$this->connection->commit();
	}

	public function dropSchema() {
		$this->connection->beginTransaction();
    	foreach ($this->schmaBuilder->getAllViews() as $v) {
    		$this->connection->executeStatement($this->dialect->dropViewToString($v));
    	}
		foreach ($this->schmaBuilder->getAllTables() as $t) {
			$this->connection->executeStatement($this->dialect->dropTableToString($t));
		}
    	$this->connection->commit();
	}
}