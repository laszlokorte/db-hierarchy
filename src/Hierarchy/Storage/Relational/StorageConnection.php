<?php

namespace App\Hierarchy\Storage\Relational;

use App\Hierarchy\Storage\Relational\Service;

use App\Hierarchy\Schema\Definition\SchemaDefinition;

use App\Hierarchy\Storage\Relational\Dialect\Sqlite;
use App\Hierarchy\Storage\Relational\Dialect\MySql;

use Doctrine\DBAL\Connection;

class StorageConnection {
	public function __construct(SchemaDefinition $schemaDef, Connection $connection) {
		$this->quirks = new Quirks(
			$connection->getDatabasePlatform()->getName() == 'mysql'
		);

		$this->schemaDef = $schemaDef;
		$this->connection = $connection;
		$this->naming = new Naming($schemaDef);
		$this->coder = new ColumnCoder($schemaDef, $this->naming);
		switch($connection->getDatabasePlatform()->getName()) {
			case 'mysql': $this->dialect = new MySql(); break;
			case 'sqlite': $this->dialect = new Sqlite(); break;
			default: throw new \Exception(sprintf('database "%s" not supported', $connection->getDatabasePlatform()->getName()));
		}
	}

	// public function getCommander() {
	// 	return new Commander($this->schemaDef, new CommandBuilder($this->schemaDef, $this->naming, $this->coder), $this->connection, $this->dialect, $this->coder);
	// }

	// public function getInstaller() {
	// 	return new Installer(new SchemaBuilder($this->schemaDef, $this->naming, $this->quirks), $this->connection, $this->dialect);
	// }

	public function getQueryService() {
		return new Service\QueryService(
			$this->schemaDef, 
			new Service\QueryCommandBuilder($this->schemaDef, $this->naming, $this->coder), 
			$this->connection, 
			$this->dialect,
			$this->coder
		);
	}

	public function getDeletionService() {
		return new Service\DeletionService(
			$this->schemaDef, 
			new Service\DeletionCommandBuilder($this->schemaDef, $this->naming, $this->coder), 
			$this->connection, 
			$this->dialect,
			$this->coder
		);
	}

	public function getCreationService() {
		return new Service\CreationService(
			$this->schemaDef, 
			new Service\CreationCommandBuilder($this->schemaDef, $this->naming, $this->coder), 
			$this->connection, 
			$this->dialect,
			$this->coder
		);
	}

	public function getUpdateService() {
		return new Service\UpdateService(
			$this->schemaDef, 
			new Service\UpdateCommandBuilder($this->schemaDef, $this->naming, $this->coder), 
			$this->connection, 
			$this->dialect,
			$this->coder
		);
	}

	public function getMovementService() {
		return new Service\MovementService(
			$this->schemaDef, 
			new Service\MovementCommandBuilder($this->schemaDef, $this->naming, $this->coder), 
			$this->connection, 
			$this->dialect,
			$this->coder
		);
	}

	public function getOrderingService() {
		return new Service\OrderingService(
			$this->schemaDef, 
			new Service\OrderingCommandBuilder($this->schemaDef, $this->naming, $this->coder), 
			$this->connection, 
			$this->dialect,
			$this->coder,
			$this->getQueryService()
		);
	}

	public function getRepairService() {
		return new Service\RepairService(
			$this->schemaDef, 
			new Service\RepairCommandBuilder($this->schemaDef, $this->naming, $this->coder), 
			$this->connection, 
			$this->dialect,
			$this->coder
		);
	}

	public function getInstallationService() {
		return new Service\InstallationService(
			new SchemaBuilder($this->schemaDef, $this->naming, $this->quirks), 
			$this->connection, 
			$this->dialect
		);
	}
}