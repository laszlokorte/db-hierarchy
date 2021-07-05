<?php

namespace App\Hierarchy\Storage\Relational\Service;

use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Storage\Relational\Dialect\DialectInterface;
use App\Hierarchy\Storage\Relational\ColumnCoder;

use Doctrine\DBAL\Connection;

class OrderingService {
	public function __construct(private SchemaDefinition $schemaDef, private Connection $connection, private DialectInterface $dialect, private ColumnCoder $coder) {

	}

	public function validateOrderNode(string $keyId, string $nodeId, $targetPosition) {
		$scopeId = null; 
		$parentId = null;

		// check order

		return new Validation(
			$keyId, 
			$nodeId, 
			null,
			[],
			$scopeId, 
			$parentId
		);
	}

	public function findNodeSiblings(string $keyId, string $nodeId) {
		$directParent = $this->findNodeDirectParent($keyId, $nodeId);

		$self = $this->findNode($keyId, $nodeId);

		if(!empty($self->hasParent())) {
			return $this->findNodeChildren($keyId, $self->getParent(), $keyId);
		} else if($self->hasScope()) {
			return $this->findNodeChildren($this->schemaDef->getKeyScopeId($keyId), $self->getScope(), $keyId);
		} else {
			return $this->findRootNodes($keyId);
		}
	}

	public function orderNode(string $keyId, $nodeId, $targetPosition) {
		$idParam = new Parameter('_id');
		$orderParam = new Parameter('_order');

		if(empty($targetPosition)) {
			throw new \Exception("target position must not be empty");
		}

		$update = $this->commandBuilder->getUpdateforReorderNode($keyId, $idParam, $orderParam);

		$this->beginTransaction();
		$stmt = $this->connection->prepare($this->dialect->updateToString($update));
		$stmt->bindValue($this->dialect->parameterToString($idParam), $nodeId, $this->coder->getPrimaryColumnBindingType($keyId));
		$stmt->bindValue($this->dialect->parameterToString($orderParam), $targetPosition, \PDO::PARAM_INT);

		$stmt->execute();
		$this->commitTransaction();
	}
}