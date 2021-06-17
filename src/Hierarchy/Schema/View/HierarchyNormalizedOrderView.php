<?php

namespace App\Hierarchy\Schema\View;

use App\Hierarchy\Schema\Definition\SchemaDefinition;

class HierarchyNormalizedOrderView {
	public function __construct(
		private HierarchyView $hierarchy,
	) {
	}

	public function getViewName() {
		return sprintf('%s_normalized_order', $this->hierarchy->getViewName());
	}

	public function getSelectStatement() {
		$orderDirection = $this->hierarchy->getOrderDirection();
		$reverseDirection = $orderDirection == 'ASC' ? 'DESC' : 'ASC';

		$hierarchyName = $this->hierarchy->getViewName();

		$placeholders = [
			'{{direction}}' => $reverseDirection,
			'{{hierarchy}}' => $hierarchyName,
		];
		return str_replace(array_keys($placeholders), array_values($placeholders), <<<SQL
			SELECT 
				h._id AS _id,
				h._order AS _stored_order,
				h._parent AS _parent,
				h._order AS _scope,
				ROW_NUMBER() OVER(
					PARTITION BY h._parent, h._order
					ORDER BY h._order {{direction}}, h._id DESC
				) AS _normalized_order 
			FROM {{hierarchy}} h 
		SQL);
	}

	public function normalizeAllStatement() {
		$selfTableName = $this->hierarchy->getTableName();
		$orderColumn = $this->hierarchy->getOrderColumn();

		$placeholders = [
			'{{order}}' => $orderColumn,
			'{{self_table}}' => $selfTableName,
			'{{view_name}}' => $this->getViewName(),
		];

		return str_replace(array_keys($placeholders), array_values($placeholders), <<<SQL
			UPDATE {{self_table}} AS outer
			SET "{{order}}" = _normalized_order FROM 
			(SELECT _id AS inner_id, _normalized_order FROM {{view_name}} WHERE _stored_order <> _normalized_order) 
			WHERE outer.id = inner_id
		SQL);
	}

	public function normalizeSomeStatement() {
		$selfTableName = $this->hierarchy->getTableName();
		$orderColumn = $this->hierarchy->getOrderColumn();

		$placeholders = [
			'{{order}}' => $orderColumn,
			'{{self_table}}' => $selfTableName,
			'{{view_name}}' => $this->getViewName(),
		];

		return str_replace(array_keys($placeholders), array_values($placeholders), <<<SQL
			UPDATE {{self_table}} AS outer
			SET "{{order}}" = _normalized_order FROM 
			(SELECT _id AS inner_id, _normalized_order FROM {{view_name}} WHERE _stored_order <> _normalized_order AND _scope IS :scope AND _parent IS parent) 
			WHERE outer.id = inner_id
		SQL);
	}
}