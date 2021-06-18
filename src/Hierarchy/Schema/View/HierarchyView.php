<?php

namespace App\Hierarchy\Schema\View;

use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Schema\Definition\ColumnDefinition;
use App\Hierarchy\Schema\Table\ClosureTable;

class HierarchyView {
	public function __construct(
		private SchemaDefinition $def, 
		private string $keyId
	) {
	}

	public function getOrderDirection() {
		return $this->def->getKeyOrderDirection($this->keyId);
	}

	public function getOrderColumn() {
		return $this->def->getKeyOrderColumn($this->keyId);
	}

	public function getTableName() {
		return $this->def->getKeyTableName($this->keyId);
	}

	public function getViewName() {
		return sprintf('_%s_hierarchy', $this->def->getKeyTableName($this->keyId));
	}

	public function getSelectStatement() {
		$closureTable = new ClosureTable($this->def, $this->keyId);

		$queryTemplate = <<<SQL
			SELECT
				self.{{scope_source}} AS _scope, ---SCOPEONLY NULL AS _scope,
				parent.{{self_id}} AS _parent, ---REFLEXONLY NULL AS _parent,
				self.{{order_column}} AS _order, ---ORDERONLY NULL AS _order,
				self.{{self_id}} AS _id
			FROM
				{{self_table}} self
				INNER JOIN {{scope_table}} scope ---SCOPEONLY
				ON scope.{{scope_target}} = self.{{scope_source}} ---SCOPEONLY
				INNER JOIN {{closure_table}} reflexive ---REFLEXONLY
				ON reflexive.{{closure_depth}} = 0 ---REFLEXONLY
				AND reflexive.{{closure_child}} = self.{{self_id}} ---REFLEXONLY
				AND reflexive.{{closure_parent}} = self.{{self_id}} ---REFLEXONLY
				LEFT JOIN {{closure_table}} closure ---REFLEXONLY
				ON closure.{{closure_child}} = self.{{self_id}} AND closure.{{closure_depth}} = 1 ---REFLEXONLY
				LEFT JOIN {{self_table}} parent ---REFLEXONLY
				ON parent.{{self_id}} = closure.{{closure_parent}} ---REFLEXONLY
			ORDER BY
			    parent.{{self_id}} ASC,  ---REFLEXONLY
			    self.{{order_column}} {{order_direction}}, ---ORDERONLY
				self.{{self_id}} ASC
		SQL;

		if($this->def->isKeyScoped($this->keyId)) {
			$queryTemplate = preg_replace("/( *)([^\n]*)\-\-\-+SCOPEONLY *([^\n]*)(\n?)/", "$1$2$4", $queryTemplate);

			$scopeKeyId = $this->def->getKeyScopeId($this->keyId);
			$scopeColumn = $this->def->getKeyScopeColumn($this->keyId);
			$scopeTableName = $this->def->getKeyTableName($scopeKeyId);

			$ownColumn = $this->def->getKeyScopeColumn($this->keyId);
			$targetColumn = $this->def->getKeyIdentityColumn($scopeKeyId);

			$scopeSourceName = $scopeColumn->getName();
			$scopeTargetName = $targetColumn->getName();
		} else {			
			$queryTemplate = preg_replace("/( *)([^\n]*)\-\-\-+SCOPEONLY *([^\n]*)(\n?)/", "$1$3", $queryTemplate);
			$scopeSourceName = '___WARNING___';
			$scopeTableName = '___WARNING___';
			$scopeTargetName = '___WARNING___';
		}


		$selfTableName = $this->def->getKeyTableName($this->keyId);
		$selfIdColumn = $this->def->getKeyIdentityColumn($this->keyId);

		if($this->def->isKeyOrdered($this->keyId)) {
			$queryTemplate = preg_replace("/( *)([^\n]+)\-\-\-+ORDERONLY *([^\n]*)(\n?)/", "$1$2$4", $queryTemplate);

			$orderColumn = $this->def->getKeyOrderColumn($this->keyId)->getName();
			$orderDirection = $this->def->getKeyOrderDirection($this->keyId);
		} else {
			$queryTemplate = preg_replace("/( *)([^\n]+)\-\-\-+ORDERONLY *([^\n]*)(\n?)/", "$1$3", $queryTemplate);
			$orderColumn = '___WARNING___';
			$orderDirection = '___WARNING___';
		}

		if($this->def->isKeyOrdered($this->keyId)) {
			$queryTemplate = preg_replace("/( *)([^\n]+)\-\-\-+ORDERONLY *([^\n]*)(\n?)/", "$1$2$4", $queryTemplate);

			$orderColumn = $this->def->getKeyOrderColumn($this->keyId)->getName();
			$orderDirection = $this->def->getKeyOrderDirection($this->keyId);
		} else {
			$queryTemplate = preg_replace("/( *)([^\n]+)\-\-\-+ORDERONLY *([^\n]*)(\n?)/", "$1$3", $queryTemplate);
			$orderColumn = '___WARNING___';
			$orderDirection = '___WARNING___';
		}

		if($this->def->isKeyReflexive($this->keyId)) {
			$queryTemplate = preg_replace("/( *)([^\n]+)\-\-\-+REFLEXONLY *([^\n]*)(\n?)/", "$1$2$4", $queryTemplate);
			$closureDepth = $closureTable->getDepthColumn()->getName();
			$closureChild = $closureTable->getChildColumn()->getName();
			$closureParent = $closureTable->getParentColumn()->getName();
			$closureTableName = $closureTable->getTableName();
		} else {
			$queryTemplate = preg_replace("/( *)([^\n]+)\-\-\-+REFLEXONLY *([^\n]*)\n?/", "$1$3", $queryTemplate);
			$closureDepth = '___WARNING___';
			$closureChild = '___WARNING___';
			$closureParent = '___WARNING___';
			$closureTableName = '___WARNING___';
		}


		$placeholders = [
			'{{closure_depth}}' => $closureDepth,
			'{{closure_child}}' => $closureChild,
			'{{closure_parent}}' => $closureParent,
			'{{scope_source}}' => $scopeSourceName,
			'{{scope_target}}' => $scopeTargetName,
			'{{scope_table}}' => $scopeTableName,
			'{{closure_table}}' => $closureTableName,
			'{{self_table}}' => $selfTableName,
			'{{self_id}}' => $selfIdColumn->getName(),
			'{{order_column}}' => $orderColumn,
			'{{order_direction}}' => $orderDirection,
		];

		return str_replace(array_keys($placeholders), array_values($placeholders), $queryTemplate);
	}
}