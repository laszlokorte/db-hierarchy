<?php

namespace App\Hierarchy\Schema;

use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Data\Node;
use App\Hierarchy\Data\NodeCollection;

class Field {
	public function __construct(
		private SchemaDefinition $def, 
		private string $keyId, 
		private string $fieldId
	) {
	}

	public function getId() {
		return $this->fieldId;
	}

	public function getKey() {
		return new Key($this->def, $this->keyId);
	}

	public function getLabel() {
		return $this->def->getKeyFieldLabel($this->keyId, $this->fieldId);
	}

	public function isRequired() {
		return $this->def->isKeyFieldRequired($this->keyId, $this->fieldId);
	}

	public function isUnique() {
		return $this->def->isKeyFieldUnique($this->keyId, $this->fieldId);
	}

	public function readValueOf(Node $node) {
		return implode(';', array_map(fn($col) => $node->getColumnValue($col->getName()), $this->getColumns()));
	}

	public function readValueOfCollection(NodeCollection $collection, $nodeId) {
		return implode(';', array_map(fn($col) => $collection->getColumnValue($nodeId, $col->getName()), $this->getColumns()));
	}

	private function getColumns() {
		$type = $this->def->getKeyFieldType($this->keyId, $this->fieldId);
		$options = $this->def->getKeyFieldOptions($this->keyId, $this->fieldId);

		return $type->getColumns($this->fieldId, $options);
	}
}