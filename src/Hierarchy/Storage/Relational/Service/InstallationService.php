<?php

namespace App\Hierarchy\Storage\Relational\Service;

use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Storage\Relational\Dialect\DialectInterface;
use App\Hierarchy\Storage\Relational\SchemaBuilder;
use App\Hierarchy\Storage\Relational\Quirks;

use Doctrine\DBAL\Connection;

class InstallationService {
	public function __construct(private InstallationCommandBuilder $commandBuilder, private Connection $connection, private DialectInterface $dialect, private Quirks $quirks) {

	}

	public function getSchemaDeclerations() {
		$schema = '';

		foreach ($this->commandBuilder->getAllTables() as $t) {
			$schema .= PHP_EOL . $this->dialect->createTableToString($t);
		}
		foreach ($this->commandBuilder->getAllViews() as $v) {
			$schema .= PHP_EOL . $this->dialect->createViewToString($v);
		}

		return $schema;
	}

	public function createSchema($dropOld, $onlyViews) {
		$turnOffFk = $this->dialect->stringSwitchForeignKey(false);
		$turnOnFk = $this->dialect->stringSwitchForeignKey(true);

		if($turnOffFk) {
			$this->connection->exec($turnOffFk);
		}

		foreach (array_reverse($this->commandBuilder->getAllViews()) as $v) {
    		$this->connection->executeStatement($this->dialect->dropViewToString($v));
    	}

		if(!$onlyViews) {
			foreach (array_reverse($this->commandBuilder->getAllTables()) as $t) {
				$this->connection->executeStatement($this->dialect->dropTableToString($t));
			}

			foreach ($this->commandBuilder->getAllTables() as $t) {
				$this->connection->executeStatement($this->dialect->createTableToString($t, !$this->quirks->noAlteredFK()));
			}

			if(!$this->quirks->noAlteredFK()) {
				foreach ($this->commandBuilder->getAllTables() as $t) {
					if($t->hasForeignKeys()) {
						$this->connection->executeStatement($this->dialect->addForeignKeysTableToString($t));
					}
				}
			}
		}

		foreach ($this->commandBuilder->getAllViews() as $v) {
			$this->connection->executeStatement($this->dialect->createViewToString($v));
		}

		if($turnOnFk) {
			$this->connection->exec($turnOnFk);
		}
	}

	public function dropSchema() {
		$turnOffFk = $this->dialect->stringSwitchForeignKey(false);
		$turnOnFk = $this->dialect->stringSwitchForeignKey(true);

		if($turnOffFk) {
			$this->connection->exec($turnOffFk);
		}

    	foreach (array_reverse($this->commandBuilder->getAllViews()) as $v) {
    		$this->connection->executeStatement($this->dialect->dropViewToString($v));
    	}
		foreach (array_reverse($this->commandBuilder->getAllTables()) as $t) {
			$this->connection->executeStatement($this->dialect->dropTableToString($t));
		}

		if($turnOnFk) {
			$this->connection->exec($turnOnFk);
		}
	}

	public function getTableDiff() {
		
		$stmt = $this->connection->prepare($this->dialect->stringQueryTableNames());
		$stmtResult = $stmt->execute();
		$existing = $stmtResult->fetchFirstColumn();
		$needed = array_map(fn($t) => $t->getName()->getString(), $this->commandBuilder->getAllTables());

		return [
			'missing' => array_diff($needed, $existing),
			'leftOver' => array_diff($existing, $needed),
			'installed' => array_diff($needed, array_diff($needed, $existing)),
		];
	}

	public function getViewDiff() {
		$stmt = $this->connection->prepare($this->dialect->stringQueryViewNames());
		$stmtResult = $stmt->execute();
		$existing = $stmtResult->fetchFirstColumn();
		$needed = array_map(fn($t) => $t->getName()->getString(), $this->commandBuilder->getAllViews());

		return [
			'missing' => array_diff($needed, $existing),
			'leftOver' => array_diff($existing, $needed),
			'installed' => array_diff($needed, array_diff($needed, $existing)),
		];
	}
}