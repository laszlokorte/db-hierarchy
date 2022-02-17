<?php

namespace App\Hierarchy\Storage\Relational\Service;

use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Storage\Relational\Dialect\DialectInterface;
use App\Hierarchy\Storage\Relational\ColumnCoder;
use App\Hierarchy\Data;


use App\Hierarchy\Storage\Relational\Algebra\Insert;
use App\Hierarchy\Storage\Relational\Algebra\Update;
use App\Hierarchy\Storage\Relational\Algebra\Delete;

use Doctrine\DBAL\Connection;

class RepairService {

	const MAX_REPAIR_RETRIES = 5;

	public function __construct(private SchemaDefinition $schemaDef, private RepairCommandBuilder $commandBuilder, private Connection $connection, private DialectInterface $dialect, private ColumnCoder $coder) {

	}

	public function findAllDefects() {
		return array_map(fn($key) => $this->findDefectsForKeyInternal($key), $this->commandBuilder->getDiagnosableKeys());
	}

	public function findDefectsForKey(string $keyId) {
		$this->connection->setAutoCommit(false);
		$result = $this->findDefectsForKeyInternal($keyId);
    	$this->connection->setAutoCommit(true);

    	return $result;
	}

	private function findDefectsForKeyInternal(string $keyId) {
		$rows = [];
		$columns = [];
		foreach($this->commandBuilder->getDiagnosisQueriesForKey($keyId) AS $name => $select) {
			$stmt = $this->connection->prepare($this->dialect->selectToString($select));
			$stmtResult = $stmt->execute();
			$rows[$name] = $stmtResult->fetchAll();
			$columns[$name] = $this->extractColumnNamesFromSelect($select);
		}

    	return new Data\Diagnostic($keyId, $rows, $columns);
	}

	private function extractColumnNamesFromSelect($select) {
		$projections = $select->getProjections();
		return array_map(fn($proj, $i) => $proj->getAutoName($i)->getString(), $projections, array_keys($projections));
	}


	public function getRepairableKeys() {
		return array_filter($this->schemaDef->getAllKeyIdsTopological(), 
			fn($keyId) => $this->schemaDef->isKeyReflexive($keyId) || $this->schemaDef->isKeyOrdered($keyId)
		);
	}

	public function repairAll() {
		$this->connection->setAutoCommit(false);
		foreach ($this->getRepairableKeys() as $key) {
			$this->repairKeyInternal($key);
		}
    	$this->connection->setAutoCommit(true);
	}

	public function repairKey(string $keyId) {
		$this->connection->setAutoCommit(false);
		$result = $this->repairKeyInternal($keyId);
    	$this->connection->setAutoCommit(true);

    	return $result;
	}

	private function repairKeyInternal(string $keyId) {
		$commands = $this->commandBuilder->getCommandForRepairKey($keyId);
		

		foreach ($commands as $label => $command) {
			$retriesLeft = self::MAX_REPAIR_RETRIES;

			while($retriesLeft-- > 0) {
				switch (get_class($command)) {
					case Insert::class:
						$stmt = $this->connection->prepare($this->dialect->insertToString($command));
						break;
					case Update::class:
						$stmt = $this->connection->prepare($this->dialect->updateToString($command));
						break;
					
					case Delete::class:
						$stmt = $this->connection->prepare($this->dialect->deleteToString($command));
						break;

					default: throw new \Exception("invalid command");
				}
				$result = $stmt->execute();

				if($result->rowCount() < 1) {
					break;
				}

			}

			if(!$retriesLeft) {
				throw new \Exception('repair timed out');
			}
		}
	}
}