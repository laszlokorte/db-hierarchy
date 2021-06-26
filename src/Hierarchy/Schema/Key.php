<?php

namespace App\Hierarchy\Schema;

use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Data\Node;

class Key {
	public function __construct(
		private SchemaDefinition $def, 
		private string $keyId
	) {
	}

	public function getId() {
		return $this->keyId;
	}

	public function hasField(string $fieldId) {
		return $this->def->keyFieldExists($this->keyId, $fieldId);
	}

	public function getField(string $fieldId) {
		return new Field($this->def, $this->keyId, $fieldId);
	}

	public function getFields() {
		return array_map([$this, 'getField'], $this->def->getKeyFieldIds($this->keyId));
	}

	public function getLabel() {
		return $this->def->getKeyLabel($this->keyId);
	}

	public function isReflexive() {
		return $this->def->isKeyReflexive($this->keyId);
	}

	public function isOrdered() {
		return $this->def->isKeyOrdered($this->keyId);
	}

	public function getOrderColumnName() {
		return $this->def->getKeyOrderColumnName($this->keyId);
	}

	public function isScoped() {
		return $this->def->isKeyScoped($this->keyId);
	}

	public function getScopeKey() {
		return new Key($this->def, $this->def->getKeyScopeId($this->keyId));
	}

	public function getScopeChildKeys() {
		return array_map(
			fn($k) => new Key($this->def, $k),
			$this->def->getKeyIdsScopedInside($this->keyId)
		);
	}

	public function getNestedKeys() {
		return array_map(
			fn($k) => new Key($this->def, $k),
			$this->def->getKeyIdsScopedInsideAndReflexiveSelf($this->keyId)
		);
	}

	public function isNested() {
		return $this->def->isKeyNested($this->keyId);
	}

	public function getReferencingKeys() {
		return array_map(
			fn($k) => new Key($this->def, $k),
			$this->def->getReferencingKeys($this->keyId)
		);
	}

	public function getSummary() {
		return $this->def->getKeySummary($this->keyId);
	}

	public function summarize(Node $node, $appendId = null) {
		$summDef = $this->def->getKeySummary($this->keyId);
		$constants = $summDef->getConstants();
		$fieldIds = $summDef->getFieldIds();

		$result = '';

		for ($i=0; $i < count($constants); $i++) {
			if($i>0) {
				$result .= $this->getField($fieldIds[$i-1])->readValueOf($node);
			}
			$result .= $constants[$i];
		}

		if($appendId === true || $appendId === null && empty($result)) {
			$result .= ' ['.$node->getKey().'-'.$node->getID().']';
		}

		return $result;
	}
}