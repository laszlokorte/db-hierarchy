<?php

namespace App\Hierarchy\Storage\Relational;

use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Storage\Relational\Dialect\DialectInterface;

use Doctrine\DBAL\Connection;

use App\Hierarchy\Storage\Relational\Algebra\Insert;
use App\Hierarchy\Storage\Relational\Algebra\Update;
use App\Hierarchy\Storage\Relational\Algebra\Delete;
use App\Hierarchy\Storage\Relational\Algebra\Value\Parameter;

class Commander {
	private const MAX_REPAIR_RETRIES = 5;

	public function __construct(private SchemaDefinition $schemaDef, private CommandBuilder $commandBuilder, private Connection $connection, private DialectInterface $dialect) {

	}

	public function createNode(string $keyId, $fieldData, $scopeId, $parentId) {
		$insert = $this->commandBuilder->getCommandForCreateNode($keyId);

		$this->connection->beginTransaction();
		$stmt = $this->connection->prepare($this->dialect->insertToString($insert));
    	
    	if($this->schemaDef->isKeyScoped($keyId)) {
			$stmt->bindValue(
				$this->dialect->parameterToString(new Parameter('_scope')),
				$scopeId
			);
		}

		foreach ($this->schemaDef->getKeyFieldIds($keyId) as $fieldId) {
			$fieldType = $this->schemaDef->getKeyFieldType($keyId, $fieldId);
			$fieldOptions = $this->schemaDef->getKeyFieldOptions($keyId, $fieldId);
			$columnData = $fieldType->fieldDataToColumnData($fieldId, $fieldOptions, $fieldData[$fieldId]);

			foreach($fieldType->getColumns($fieldId, $fieldOptions) AS $ci => $column) {
				$stmt->bindValue(
					$this->dialect->parameterToString(new Parameter($column->getName())),
					$columnData[$ci]
				);
			}
		}

    	$stmt->execute();
    	$this->connection->commit();
	}

	public function updateNode(string $keyId) {
		
	}

	public function deleteNode(string $keyId, $nodeId) {
		
	}

	public function moveNode(string $keyId, $nodeId, $targetScopeId, $targetParentId) {
		
	}

	public function repairAll() {
		$this->connection->beginTransaction();
		foreach ($this->commandBuilder->getRepairableKeys() as $key) {
			$this->repairKeyInternal($key);
		}
    	$this->connection->commit();
	}

	public function repairKey(string $keyId) {
		$this->connection->beginTransaction();
		$result = $this->repairKeyInternal($keyId);
    	$this->connection->commit();

    	return $result;
	}

	private function repairKeyInternal(string $keyId) {
		$retriesLeft = self::MAX_REPAIR_RETRIES;

		while($retriesLeft-- > 0) {
			$commands = $this->commandBuilder->getCommandForRepairKey($keyId);
			$affected = 0;

			foreach ($commands as $label => $command) {
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
				$affected += $stmt->rowCount();
			}

			if($affected < 1) {
				return;
			}
		}
	}
}