<?php

namespace App\Hierarchy\Storage\Relational;

use App\Hierarchy\Data\Validation;

use App\Hierarchy\Storage\Relational\Dialect\DialectInterface;
use App\Hierarchy\Schema\Definition\SchemaDefinition;

use Doctrine\DBAL\Connection;

class Validator {
	public function __construct(
		private SchemaDefinition $schemaDef, 
		private ValidationBuilder $queryBuilder, 
		private Connection $connection, 
		private DialectInterface $dialect
	) {

	}

	public function validateCreateNode(string $keyId, array $fieldData, ?string $scopeId, ?string $parentId) {
		
		return new Validation(
			$keyId, 
			null, 
			$fieldData,
			[], 
			$scopeId, 
			$parentId
		);
	}

	public function validateUpdateNode(string $keyId, string $nodeId, array $fieldData) {
		return new Validation(
			$keyId, 
			$nodeId, 
			$fieldData,
			[],
		);
	}

	public function validateMoveNode(string $keyId, string $nodeId, ?string $targetScopeId, ?string $targetParentId) {
		return new Validation(
			$keyId, 
			$nodeId, 
			null,
			[],
			$targetScopeId, 
			$targetParentId
		);
	}

	public function validateDeleteNode(string $keyId, string $nodeId) {
		$scopeId = null; 
		$parentId = null;

		return new Validation(
			$keyId, 
			$nodeId, 
			null,
			[],
			$scopeId, 
			$parentId
		);
	}

	public function validateOrderNode(string $keyId, string $nodeId, $targetPosition) {
		$scopeId = null; 
		$parentId = null;

		return new Validation(
			$keyId, 
			$nodeId, 
			null,
			[],
			$scopeId, 
			$parentId
		);
	}
}