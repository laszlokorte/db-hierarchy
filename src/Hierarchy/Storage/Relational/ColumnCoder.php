<?php

namespace App\Hierarchy\Storage\Relational;


use App\Hierarchy\Schema\Definition\SchemaDefinition;

use App\Hierarchy\Storage\Relational\Algebra\Operator\Function\Hex;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Function\Unhex;
use App\Hierarchy\Storage\Relational\Algebra\Value\Parameter;
use App\Hierarchy\Storage\Relational\Algebra\Value\FunctionApplication;
use App\Hierarchy\Storage\Relational\Algebra\Value\ColumnReference;
use App\Hierarchy\Storage\Relational\Algebra\Value\Constant;
use App\Hierarchy\Storage\Relational\Algebra\TableReference;
use App\Hierarchy\Storage\Relational\Algebra\Identifier;
use App\Hierarchy\Schema\Definition\ColumnDefinition;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

class ColumnCoder {
	public function __construct(private SchemaDefinition $schemaDef, private Naming $naming) {

	}

	public function wrapPrimaryKeyParameter($keyId, Parameter|Constant $parameter) {
		return $this->encodeColumnType($this->schemaDef->getKeyIdentityColumnType($keyId), $parameter);
	}

	public function wrapScopeParameter($keyId, Parameter|Constant $parameter) {
		$scopeId = $this->schemaDef->getKeyScopeId($keyId);

		if(!$scopeId) {
			return $parameter;
		}

		return $this->wrapPrimaryKeyParameter($scopeId, $parameter);
	}

	public function wrapParentParameter($keyId, Parameter|Constant $parameter) {
		return $this->wrapPrimaryKeyParameter($keyId, $parameter);
	}

	public function wrapOrderParameter($keyId, Parameter $parameter) {
		return $parameter;
	}

	public function wrapPrimaryColumn($keyId, $tableRef) {
		$columnRef = new ColumnReference($tableRef, $this->naming->nodeTablePKName($keyId));

		return $this->decodeColumnType($this->schemaDef->getKeyIdentityColumnType($keyId), $columnRef);
	}

	public function wrapScopeColumn($keyId, $tableRef) {
		$columnRef = new ColumnReference($tableRef, $this->naming->nodeOwnScopeColumnName($keyId));

		return $this->decodeColumnType($this->schemaDef->getKeyScopeColumnType($keyId), $columnRef);
	}

	public function wrapOrderColumn($keyId, $tableRef) {
		$columnRef = new ColumnReference($tableRef, $this->naming->orderColumnName($keyId));

		return $columnRef;
	}

	public function wrapColumn(ColumnDefinition $column, $tableRef) {
		if($column->isReference()) {
			$refType = $this->schemaDef->getKeyIdentityColumnType($column->getCoding()->getTarget());

			return $this->decodeColumnType($refType, new ColumnReference($tableRef, $this->naming->fieldColumnToName($column)));
		} else {
			return $this->decodeColumnType($column->getCoding()->getType(), new ColumnReference($tableRef, $this->naming->fieldColumnToName($column)));
		}
	}

	public function wrapColumnParameter(ColumnDefinition $column, $parameter) {
		if($column->isReference()) {
			$refType = $this->schemaDef->getKeyIdentityColumnType($column->getCoding()->getTarget());

			return $this->encodeColumnType($refType, $parameter);
		} else { 
			return $this->encodeColumnType($column->getCoding()->getType(), $parameter);
		}
	}

	public function wrapClosureParentColumn($keyId, $tableRef) {
		$columnRef = new ColumnReference($tableRef, $this->naming->closureParentColumnName($keyId));

		return $this->decodeColumnType($this->schemaDef->getKeyIdentityColumnType($keyId), $columnRef);
	}

	public function wrapClosureChildColumn($keyId, $tableRef) {
		$columnRef = new ColumnReference($tableRef, $this->naming->closureChildColumnName($keyId));

		return $this->decodeColumnType($this->schemaDef->getKeyIdentityColumnType($keyId), $columnRef);
	}

	public function wrapClosureDepthColumn($keyId, $tableRef) {
		$columnRef = new ColumnReference($tableRef, $this->naming->closureTableDepthName($keyId));

		return $columnRef;
	}

	public function wrapHierarchyPrimaryColumn($keyId, $tableRef) {
		$columnRef = new ColumnReference($tableRef, $this->naming->hierarchyIdColumnName($keyId));

		return $this->decodeColumnType($this->schemaDef->getKeyIdentityColumnType($keyId), $columnRef);
	}

	public function wrapHierarchyScopeColumn($keyId, $tableRef) {
		$columnRef = new ColumnReference($tableRef, $this->naming->hierarchyScopeColumnName($keyId));

		if(!$this->schemaDef->isKeyScoped($keyId)) {
			return $columnRef;
		}

		return $this->decodeColumnType($this->schemaDef->getKeyScopeColumnType($keyId), $columnRef);
	}

	public function wrapHierarchyOrderColumn($keyId, $tableRef) {
		$columnRef = new ColumnReference($tableRef, $this->naming->hierarchyOrderColumnName($keyId));

		return $columnRef;
	}

	public function wrapHierarchyParentColumn($keyId, $tableRef) {
		$columnRef = new ColumnReference($tableRef, $this->naming->hierarchyParentColumnName($keyId));

		return $this->decodeColumnType($this->schemaDef->getKeyIdentityColumnType($keyId), $columnRef);
	}

	public function getPrimaryColumnBindingType($keyId) {
		return $this->getColumnBindingType($this->schemaDef->getKeyIdentityColumnType($keyId));
	}

	public function getScopeColumnBindingType($keyId) {
		return $this->getPrimaryColumnBindingType($this->schemaDef->getKeyScopeId($keyId));
	}

	public function getParentColumnBindingType($keyId) {
		return $this->getPrimaryColumnBindingType($keyId);
	}

	public function getOrderColumnBindingType($keyId) {
		return StorageCoding::INTEGER;
	}

	public function getColumnDefinitionBindingType(ColumnDefinition $column) {
		if($column->isReference()) {
			$refType = $this->schemaDef->getKeyIdentityColumnType($column->getCoding()->getTarget());

			return $this->getColumnBindingType($refType);
		} else { 
			return $this->getColumnBindingType($column->getCoding()->getType());
		}
	}

	private function getColumnBindingType(string $type) {
		switch($type) {
			case 'serial':
				return ParameterType::INTEGER;
			case 'uuid':
				return ParameterType::STRING;
			case 'manual':
			default:
				return ParameterType::STRING;
		}
	}

	public function decodeColumnType(string $type, $value) {
		switch($type) {
			case 'serial':
				return $value;
			case 'uuid':
				return new FunctionApplication(new Hex(), [$value]);
			case 'manual':
				return $value;
			default:
				return $value;
		}
	}

	public function encodeColumnType($type, Parameter|Constant $value) {
		if($value instanceof Constant && $value->isNull()) {
			return $value;
		}
		switch($type) {
			case 'serial':
				return $value;
			case 'uuid':
				return new FunctionApplication(new Unhex(), [$value]);
			case 'manual':
				return $value;
			default:
				return $value;
		}
	}
}