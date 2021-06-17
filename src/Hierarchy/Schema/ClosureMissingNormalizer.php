<?php

namespace App\Hierarchy\Schema;

use App\Hierarchy\Schema\Definition\SchemaDefinition;

class ClosureMissingNormalizer {
	public function __construct(
		private SchemaDefinition $def, 
		private string $keyId
	) {
	}

	public function getViewName() {
		return sprintf('%s_missing', $this->def->getKeyReflexivityTable($this->keyId));
	}

	public function getSelectStatement() {
		if($this->def->isKeyScoped($this->keyId)) {
			return <<<SQL
				SELECT
					NULL AS id,
					a.%SCOPE%_id AS %SCOPE%_id,
					a.parent_id AS parent_id,
					b.child_id AS child_id,
					a.depth + b.depth AS depth,
					"transitivity" AS reason
				FROM
					%NAME%_closure a,
					%NAME%_closure b
				WHERE
					a.id <> b.id
					AND
					a.%SCOPE%_id = b.%SCOPE%_id
					AND
					b.parent_id = a.child_id
					AND
					NOT EXISTS (
						SELECT id
						FROM %NAME%_closure t WHERE
						(t.parent_id, t.child_id, t.depth)
						IS (a.parent_id, b.child_id, a.depth + b.depth)
						AND
						t.%SCOPE%_id = a.%SCOPE%_id
					)
				UNION
				SELECT
					NULL AS id,
					m.%SCOPE%_id AS %SCOPE%_id,
					id AS parent_id,
					id AS child_id,
					0 AS depth,
					"reflexivity" AS reason
				FROM
					%NAME% m
				WHERE
					NOT EXISTS (
						SELECT id FROM %NAME%_closure r
						WHERE (r.parent_id, r.child_id, r.depth)
						IS (m.id, m.id, 0)
					)
			SQL;
		} else {
			return <<<SQL
				SELECT
					NULL AS id,
					a.parent_id AS parent_id,
					b.child_id AS child_id,
					a.depth + b.depth AS depth,
					"transitivity" AS reason
				FROM
					%NAME%_closure a,
					%NAME%_closure b
				WHERE
					a.id <> b.id
					AND
					b.parent_id = a.child_id
					AND
					NOT EXISTS (
						SELECT id
						FROM %NAME%_closure t WHERE
						(t.parent_id, t.child_id, t.depth)
						IS (a.parent_id, b.child_id, a.depth + b.depth)
					)
				UNION
				SELECT
					NULL AS id,
					id AS parent_id,
					id AS child_id,
					0 AS depth,
					"reflexivity" AS reason
				FROM
					%NAME% m
				WHERE
					NOT EXISTS (
						SELECT id FROM %NAME%_closure r
						WHERE (r.parent_id, r.child_id, r.depth)
						IS (m.id, m.id, 0)
					)
			SQL;
		}
	}
}