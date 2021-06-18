<?php

namespace App\Hierarchy\Schema\View;

use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Schema\Table\ClosureTable;

class ClosureFindInvalidsView {
	public function __construct(
		private SchemaDefinition $def, 
		private string $keyId
	) {
	}

	public function getViewName() {
		return sprintf('_%s_invalid', $this->def->getKeyReflexivityTableName($this->keyId));
	}

	public function getSelectStatement() {
		$closureTable = new ClosureTable($this->def, $this->keyId);

		$queryTemplate = <<<SQL
			SELECT
				{{closureColumns}}
			FROM
				{{closure_table}} t
			WHERE (t.{{closure_depth}} = 0 AND t.{{closure_child}} <> t.{{closure_parent}}) 
			OR (t.{{closure_depth}} <> 0 AND t.{{closure_child}} IS t.{{closure_parent}}) 
			OR (t.{{closure_depth}} > 1 AND NOT EXISTS (
					SELECT a.{{closure_id}}
					FROM {{closure_table}} a
					INNER JOIN {{closure_table}} b ON a.{{closure_child}} = b.{{closure_parent}}
					WHERE (a.{{closure_depth}} + b.{{closure_depth}}) = t.{{closure_depth}}
						AND a.{{closure_id}} <> t.{{closure_id}}
						AND b.{{closure_id}} <> t.{{closure_id}}
						AND (t.{{closure_parent}}, t.{{closure_child}})
						IS (a.{{closure_parent}}, b.{{closure_child}})
						AND (a.{{closure_scope}}, b.{{closure_scope}}) IS (t.{{closure_scope}}, t.{{closure_scope}}) ---SCOPEONLY
			)) 
			OR (t.{{closure_child}} <> t.{{closure_parent}} AND EXISTS (
					SELECT r.{{closure_id}}
					FROM {{closure_table}} r
					WHERE (r.{{closure_child}}, r.{{closure_parent}}) = (t.{{closure_parent}}, t.{{closure_child}})
				)
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
			'{{closureColumns}}' => implode(', '.PHP_EOL, array_map(fn($c) => str_replace('{c}', $c->getName(), 't.{c} AS {c}'), $closureTable->getColumns())),
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