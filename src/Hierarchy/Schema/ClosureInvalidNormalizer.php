<?php

namespace App\Hierarchy\Schema;

use App\Hierarchy\Schema\Definition\SchemaDefinition;

class ClosureInvalidNormalizer {
	public function __construct(
		private SchemaDefinition $def, 
		private string $keyId
	) {
	}

	public function getViewName() {
		return sprintf('%s_invalid', $this->def->getKeyReflexivityTable($this->keyId));
	}

	public function getSelectStatement() {
		if($this->def->isKeyScoped($this->keyId)) {
			return <<<SQL
				SELECT
					t.%SCOPE%_id AS %SCOPE%_id,
					t.id AS id,
					t.parent_id AS parent_id,
					t.child_id AS child_id,
					t.depth AS depth
				FROM
					%NAME%_closure t
				WHERE (t.depth = 0 AND t.child_id <> t.parent_id) 
				OR (t.depth <> 0 AND t.child_id IS t.parent_id) 
				OR (t.depth > 1 AND NOT EXISTS (
					SELECT a.id
					FROM %NAME%_closure a
					INNER JOIN %NAME%_closure b ON a.child_id = b.parent_id
					WHERE (a.%SCOPE%_id, b.%SCOPE%_id) IS (t.%SCOPE%_id, t.%SCOPE%_id)
					AND (a.depth + b.depth) = t.depth
					AND a.id <> t.id
					AND b.id <> t.id
					AND (t.parent_id, t.child_id)
					IS (a.parent_id, b.child_id)
				)) 
				OR (t.child_id <> t.parent_id AND EXISTS (
					SELECT r.id
					FROM %NAME%_closure r
					WHERE (r.child_id, r.parent_id) = (t.parent_id, t.child_id)
				))
			SQL;
		} else {
			return <<<SQL
				SELECT
					t.id AS id,
					t.parent_id AS parent_id,
					t.child_id AS child_id,
					t.depth AS depth
				FROM
					%NAME%_closure t
				WHERE (t.depth = 0 AND t.child_id <> t.parent_id ) 
				OR (t.depth <> 0 AND t.child_id IS t.parent_id) 
				OR (t.depth > 1 AND NOT EXISTS (
					SELECT a.id
					FROM %NAME%_closure a
					INNER JOIN %NAME%_closure b ON a.child_id = b.parent_id
					WHERE (a.depth + b.depth) = t.depth 
					AND a.id <> t.id
					AND b.id <> t.id
					AND (t.parent_id, t.child_id)
					IS (a.parent_id, b.child_id)
				))
			SQL;
		}
	}
}