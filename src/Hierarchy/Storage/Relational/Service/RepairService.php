<?php

namespace App\Hierarchy\Storage\Relational\Service;

use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Storage\Relational\Dialect\DialectInterface;
use App\Hierarchy\Storage\Relational\ColumnCoder;

use Doctrine\DBAL\Connection;

class RepairService {
	public function __construct(private SchemaDefinition $schemaDef, private Connection $connection, private DialectInterface $dialect, private ColumnCoder $coder) {

	}

	public function findAllDefects() {
		return array_map(fn($key) => $this->findDefectsForKeyInternal($key), $this->queryBuilder->getDiagnosableKeys());
	}

	public function findDefectsForKey(string $keyId) {
		$this->beginTransaction();
		$result = $this->findDefectsForKeyInternal($keyId);
    	$this->commitTransaction();

    	return $result;
	}

	private function findDefectsForKeyInternal(string $keyId) {
		$rows = [];
		$columns = [];
		foreach($this->queryBuilder->getDiagnosisQueriesForKey($keyId) AS $name => $select) {
			$stmt = $this->connection->prepare($this->dialect->selectToString($select));
			$stmt->execute();
			$rows[$name] = $stmt->fetchAll();
			$columns[$name] = $this->extractColumnNamesFromSelect($select);
		}

    	return new Data\Diagnostic($keyId, $rows, $columns);
	}

	private function extractColumnNamesFromSelect($select) {
		$projections = $select->getProjections();
		return array_map(fn($proj, $i) => $proj->getAutoName($i)->getString(), $projections, array_keys($projections));
	}

	public function repairAll() {
		$this->beginTransaction();
		foreach ($this->commandBuilder->getRepairableKeys() as $key) {
			$this->repairKeyInternal($key);
		}
    	$this->commitTransaction();
	}

	public function repairKey(string $keyId) {
		$this->beginTransaction();
		$result = $this->repairKeyInternal($keyId);
    	$this->commitTransaction();

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
				$stmt->execute();

				if($stmt->rowCount() < 1) {
					break;
				}

			}

			if(!$retriesLeft) {
				throw new \Exception('repair timed out');
			}
		}
	}
}