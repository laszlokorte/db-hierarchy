<?php

namespace App\Hierarchy\Storage\Relational;

use App\Hierarchy\Storage\Relational\Dialect\DialectInterface;

use Doctrine\DBAL\Connection;

use App\Hierarchy\Storage\Relational\Algebra\Insert;
use App\Hierarchy\Storage\Relational\Algebra\Update;
use App\Hierarchy\Storage\Relational\Algebra\Delete;

class Commander {
	private const MAX_REPAIR_RETRIES = 5;

	public function __construct(private CommandBuilder $commandBuilder, private Connection $connection, private DialectInterface $dialect) {

	}

	public function createNode(string $keyId, $scopeId, $parentId) {
		
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