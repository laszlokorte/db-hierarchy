<?php

namespace App\Hierarchy\Storage\Relational;

use App\Hierarchy\Schema\Definition\SchemaDefinition;

use App\Hierarchy\Storage\Relational\Dialect\Sqlite;

use Doctrine\DBAL\Connection;

class StorageConnection {
	public function __construct(SchemaDefinition $schemaDef, Connection $connection) {
		$this->schemaDef = $schemaDef;
		$this->connection = $connection;
		$this->naming = new Naming($schemaDef);
		$this->dialect = new Sqlite();
	}

	public function getCommander() {
		return new Commander(new CommandBuilder($this->schemaDef, $this->naming), $this->connection, $this->dialect);
	}

	public function getFetcher() {
		return new Fetcher(new QueryBuilder($this->schemaDef, $this->naming), $this->connection, $this->dialect);
	}

	public function getInstaller() {
		return new Installer(new SchemaBuilder($this->schemaDef, $this->naming), $this->connection, $this->dialect);
	}
}