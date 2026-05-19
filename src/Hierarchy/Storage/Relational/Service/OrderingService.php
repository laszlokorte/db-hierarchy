<?php

namespace App\Hierarchy\Storage\Relational\Service;

use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Storage\Relational\Dialect\DialectInterface;
use App\Hierarchy\Storage\Relational\ColumnCoder;

use App\Hierarchy\Changeset\Ordering;
use App\Hierarchy\Data\Node;

use App\Hierarchy\Storage\Relational\Algebra\Value\Parameter;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

class OrderingService {
	public function __construct(private SchemaDefinition $schemaDef, private OrderingCommandBuilder $commandBuilder, private Connection $connection, private DialectInterface $dialect, private ColumnCoder $coder, private QueryService $queryService) {

	}

	public function getFreshOrdering(Node $node) {
		return new Ordering(
			$node->getKey(),
			$node->getId(),
			$node->getOrder(),
			null
		);
	}

	public function getValidatedOrdering(Node $node, $targetPosition) {
		$scopeId = null; 
		$parentId = null;

		// check order

		return new Ordering(
			$node->getKey(),
			$node->getId(),
			$targetPosition,
			[]
		);
	}

	public function findNodeSiblings(string $keyId, string $nodeId) {
		return $this->queryService->findNodeSiblings($keyId, $nodeId);
	}

	public function orderNode(string $keyId, $nodeId, $targetPosition) {
		$idParam = new Parameter('_id');
		$orderParam = new Parameter('_order');

		if(empty($targetPosition)) {
			throw new \Exception("target position must not be empty");
		}

		$update = $this->commandBuilder->getUpdateforReorderNode($keyId, $idParam, $orderParam);

		$this->connection->beginTransaction();
		$stmt = $this->connection->prepare($this->dialect->updateToString($update));
		$stmt->bindValue($this->dialect->parameterToString($idParam), $nodeId, $this->coder->getPrimaryColumnBindingType($keyId));
		$stmt->bindValue($this->dialect->parameterToString($orderParam), $targetPosition, ParameterType::INTEGER);

		$stmt->execute();
		
		$this->connection->commit();
	}
}