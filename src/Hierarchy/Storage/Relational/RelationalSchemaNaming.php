<?php

namespace App\Hierarchy\Storage\Relational;

use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Schema\Definition\ColumnDefinition;
use App\Hierarchy\Storage\Relational\Algebra\Identifier;


class RelationalSchemaNaming {
	public function __construct(private SchemaDefinition $schemaDef) {

	}

	public function hierarchyViewName($keyId) {
		return new Identifier(sprintf('_%s_hierarchy', $this->schemaDef->getKeyTableName($keyId)));
	}
	public function closureInvalidViewName($keyId) {
		return new Identifier(sprintf('_%s_invalid', $this->schemaDef->getKeyReflexivityTableName($keyId)));
	}
	public function closureMissingViewName($keyId) {
		return new Identifier(sprintf('_%s_missing', $this->schemaDef->getKeyReflexivityTableName($keyId)));
	}
	public function normalizedOrderViewName($keyId) {
		return new Identifier(sprintf('_%s_hierarchy', $this->schemaDef->getKeyTableName($keyId)));
	}

	public function normalizedOrderStoredColumnName($keyId) {
		return new Identifier('_stored_order');
	}

	public function normalizedOrderIdColumnName($keyId) {
		return new Identifier('_id');
	}

	public function normalizedOrderParentColumnName($keyId) {
		return new Identifier('_parent');
	}

	public function closureParentColumnName($keyId) {
		$parentColumn = $this->schemaDef->getKeyReflexivityParentColumn($keyId);
		return new Identifier($parentColumn->getName());
	}

	public function closureChildColumnName($keyId) {
		$childColumn = $this->schemaDef->getKeyReflexivityChildColumn($keyId);
		return new Identifier($childColumn->getName());
	}

	public function normalizedOrderScopeColumnName($keyId) {
		return new Identifier('_scope');
	}

	public function normalizedOrderNormalizedColumnName($keyId) {
		return new Identifier('_normalized_order');
	}

	public function fieldColumnToName(ColumnDefinition $columnDefinition) {
		return new Identifier($columnDefinition->getName());
	}

	public function nodeTableName($keyId) {
		return new Identifier($this->schemaDef->getKeyTableName($keyId));
	}

	public function nodeTablePKName($keyId) {
		$pkColumn = $this->schemaDef->getKeyIdentityColumn($keyId);
		return new Identifier($pkColumn->getName());
	}

	public function scopeTablename($keyId) {
		return $this->nodeTableName($this->schemaDef->getKeyScopeId($keyId));
	}

	public function scopeTablePKName($keyId) {
		return $this->nodeTablePKName($this->schemaDef->getKeyScopeId($keyId));
	}

	public function nodeOwnScopeColumnName($keyId) {
		return $this->fieldColumnToName($this->schemaDef->getKeyScopeColumn($keyId));
	}

	public function closureTableName($keyId) {
		return new Identifier($this->schemaDef->getKeyReflexivityTableName($keyId));
	}

	public function closureTablePkName($keyId) {
		return new Identifier('id');
	}

	public function closureTableDepthName($keyId) {
		return new Identifier('depth');
	}

	public function hierarchyIdColumnName($keyId) {
		return new Identifier('_id');
	}

	public function hierarchyOrderColumnName($keyId) {
		return new Identifier('_order');
	}	

	public function hierarchyParentColumnName($keyId) {
		return new Identifier('_parent');
	}	

	public function hierarchyScopeColumnName($keyId) {
		return new Identifier('_scope');
	}	
}
