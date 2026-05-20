<?php

namespace App\Hierarchy\Storage\Relational\Service;

use App\Hierarchy\Data\Diagnostic;
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
    /**
     * @return Diagnostic[]
     */
    public function findAllDefects(): array {
		return array_map(fn($key) => $this->findDefectsForKeyInternal($key), $this->commandBuilder->getDiagnosableKeys());
	}

	public function findDefectsForKey(string $keyId) : Diagnostic {
		$result = $this->findDefectsForKeyInternal($keyId);

    	return $result;
	}
    /**
     * @return Diagnostic
     */
    private function findDefectsForKeyInternal(string $keyId): Diagnostic {
		$rows = [];
		$columns = [];
		foreach($this->commandBuilder->getDiagnosisQueriesForKey($keyId) AS $name => $select) {
			$stmt = $this->connection->prepare($this->dialect->selectToString($select));
			$stmtResult = $stmt->executeQuery();
			$rows[$name] = $stmtResult->fetchAllAssociative();
			$columns[$name] = $this->extractColumnNamesFromSelect($select);
		}

    	return new Data\Diagnostic($keyId, $rows, $columns);
	}
    /**
     * @return array
     * @param mixed $select
     */
    private function extractColumnNamesFromSelect($select): array {
		$projections = $select->getProjections();
		return array_map(fn($proj, $i) => $proj->getAutoName($i)->getString(), $projections, array_keys($projections));
	}
    /**
     * @return array
     */
    public function getRepairableKeys(): array {
		return array_filter($this->schemaDef->getAllKeyIdsTopological(),
			fn($keyId) => $this->schemaDef->isKeyReflexive($keyId) || $this->schemaDef->isKeyOrdered($keyId)
		);
	}
    /**
     * @return void
     */
    public function repairAll(): void {
		$this->connection->beginTransaction();
		foreach ($this->getRepairableKeys() as $key) {
			$this->repairKeyInternal($key);
		}
    	$this->connection->commit();
	}

	public function repairKey(string $keyId) :void {
		$this->connection->beginTransaction();
		$result = $this->repairKeyInternal($keyId);
    	$this->connection->commit();
	}
    /**
     * @return void
     */
    private function repairKeyInternal(string $keyId): void {
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
				$result = $stmt->executeQuery();

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
