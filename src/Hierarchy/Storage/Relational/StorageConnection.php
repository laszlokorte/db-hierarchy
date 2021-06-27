<?php

namespace App\Hierarchy\Storage\Relational;

use App\Hierarchy\Schema\Definition\SchemaDefinition;

use App\Hierarchy\Storage\Relational\Dialect\Sqlite;
use App\Hierarchy\Storage\Relational\Dialect\MySql;

use Doctrine\DBAL\Connection;

class StorageConnection {
	public function __construct(SchemaDefinition $schemaDef, Connection $connection) {
		$this->quirks = new Quirks(
			$connection->getDriver()->getName() == 'pdo_mysql'
		);

		$this->schemaDef = $schemaDef;
		$this->connection = $connection;
		$this->naming = new Naming($schemaDef);
		switch($connection->getDriver()->getName()) {
			case 'pdo_mysql': $this->dialect = new MySql(); break;
			case 'pdo_sqlite': $this->dialect = new Sqlite(); break;
			default: throw new \Exception(sprintf('database "%s" not supported', $connection->getDriver()->getName()));
		}
	}

	public function getCommander() {
		return new Commander($this->schemaDef, new CommandBuilder($this->schemaDef, $this->naming), $this->connection, $this->dialect);
	}

	public function getFetcher() {
		return new Fetcher($this->schemaDef, new QueryBuilder($this->schemaDef, $this->naming), $this->connection, $this->dialect);
	}

	public function getInstaller() {
		return new Installer(new SchemaBuilder($this->schemaDef, $this->naming, $this->quirks), $this->connection, $this->dialect);
	}
}