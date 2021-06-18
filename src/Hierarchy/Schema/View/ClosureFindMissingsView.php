<?php

namespace App\Hierarchy\Schema\View;

use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Schema\Table\ClosureTable;

class ClosureFindMissingsView {
	public function __construct(
		private SchemaDefinition $def, 
		private string $keyId
	) {
	}

	public function getViewName() {
		return sprintf('_%s_missing', $this->def->getKeyReflexivityTableName($this->keyId));
	}

	public function getSelectStatement() {
		$closureTable = new ClosureTable($this->def, $this->keyId);

		$queryTemplate = <<<SQL
			SELECT
				NULL AS id,
			    a.{{closure_scope}} AS {{closure_scope}}, ---SCOPEONLY
				a.{{closure_parent}} AS {{closure_parent}},
				b.{{closure_child}} AS {{closure_child}},
				a.{{closure_depth}} + b.{{closure_depth}} AS {{closure_depth}},
				"transitivity" AS reason
			FROM
				{{closure_table}} a,
				{{closure_table}} b
			WHERE
				a.{{closure_id}} <> b.{{closure_id}}
				AND  ---SCOPEONLY
				a.{{closure_scope}} = b.{{closure_scope}}  ---SCOPEONLY
				AND
				b.{{closure_parent}} = a.{{closure_child}}
				AND
				NOT EXISTS (
					SELECT id
					FROM {{closure_table}} t WHERE
					(t.{{closure_parent}}, t.{{closure_child}}, t.{{closure_depth}})
					IS (a.{{closure_parent}}, b.{{closure_child}}, a.{{closure_depth}} + b.{{closure_depth}})
					AND ---SCOPEONLY
					t.{{closure_scope}} = a.{{closure_scope}} ---SCOPEONLY
				)
			UNION
			SELECT
				NULL AS id,
				m.{{closure_scope}} AS {{closure_scope}}, ---SCOPEONLY
				id AS {{closure_parent}},
				id AS {{closure_child}},
				0 AS {{closure_depth}},
				"reflexivity" AS reason
			FROM
				{{closure_table}} m
			WHERE
				NOT EXISTS (
					SELECT id FROM {{closure_table}} r
					WHERE (r.{{closure_parent}}, r.{{closure_child}}, r.{{closure_depth}})
					IS (m.{{closure_id}}, m.{{closure_id}}, 0)
				)
		SQL;

		if($this->def->isKeyScoped($this->keyId)) {
			$queryTemplate = preg_replace("/( *)([^\n]*)---+SCOPEONLY *([^\n]*)(\n?)/", "$1$2", $queryTemplate);
			$scopeColumn = $this->def->getKeyScopeColumn($this->keyId);
			$scopeColumnName = $scopeColumn->getName();
		} else {
			$queryTemplate = preg_replace("/( *)([^\n]*)---+SCOPEONLY *([^\n]*)(\n?)/", "$1$3", $queryTemplate);
			$scopeColumnName = '___WARNING___';
		}

		$placeholders = [
			'{{closureColumns}}' => implode(', '.PHP_EOL, array_map(fn($c) => str_replace('{c}', $c->getName(), 's.{c} AS {c}'), $closureTable->getColumns())),
			'{{closure_depth}}' => $closureTable->getDepthColumn()->getName(),
			'{{closure_child}}' => $closureTable->getChildColumn()->getName(),
			'{{closure_parent}}' => $closureTable->getParentColumn()->getName(),
			'{{closure_scope}}' => $scopeColumnName,
			'{{closure_table}}' => $closureTable->getTableName(),
			'{{closure_id}}' => $closureTable->getPrimaryKeyColumn()->getName(),
		];

		return str_replace(array_keys($placeholders), array_values($placeholders), $queryTemplate);
	}
}