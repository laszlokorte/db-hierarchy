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

	public function getScopeChildKeys($singletons = true) {
		return array_map(
			fn($k) => new Key($this->def, $k),
			$this->def->getKeyIdsScopedInside($this->keyId, $singletons)
		);
	}

	public function getNestedKeys() {
		return array_map(
			fn($k) => new Key($this->def, $k),
			$this->def->getKeyIdsScopedInsideAndReflexiveSelf($this->keyId)
		);
	}

	public function getNestingPath() {
		return array_map(
			fn($k) => new Key($this->def, $k),
			$this->def->getKeyScopePath($this->keyId, true)
		);
	}

	public function isNested() {
		return $this->def->isKeyNested($this->keyId);
	}

	public function isSingleton() {
		return $this->def->isKeySingleton($this->keyId);
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

		$result = '';

		foreach ($summDef->getSegments() as $seg) {
			if($seg->isConstant()) {
				$val = $seg->getType();
			} elseif($seg->isLocal()) {
				if($seg->isLabel()) {
					$val = $this->getLabel()->getString();
				} else if($seg->isId()) {
					$val = $node->getId();
				} else if($seg->isField()) {
					$val = $this->getField($seg->getFieldId())->readFormattedValueOf($node);
				}
			} elseif($seg->isNested()) {
				if($seg->isId()) {
					$val = $node->getParent() ?: $node->getScope();
				} elseif($seg->isLabel()) {
					$val = $node->getParent() ? $this->getLabel()->getString() : $this->getScopeKey()->getLabel()->getString();
				} else {
					$val = '?';
				}
			} elseif($seg->isParent()) {
				if($seg->isId()) {
					$val = $node->getParent();
				} elseif($seg->isLabel()) {
					$val = $this->getLabel()->getString();
				} else {
					$val = '?';
				}
			} elseif($seg->isScope()) {
				if($seg->isId()) {
					$val = $node->getScope();
				} elseif($seg->isLabel()) {
					$val = $this->getScopeKey()->getLabel()->getString();
				} else {
					$val = '?';
				}
			}

			if(empty($val)) {
				return ' ['.$node->getKey().'-'.$node->getID().']';
			}

			$result .= $val;
		}

		if($appendId === true || $appendId === null && empty($result)) {
			$result .= ' ['.$node->getKey().'-'.$node->getID().']';
		}

		return $result;
	}
}