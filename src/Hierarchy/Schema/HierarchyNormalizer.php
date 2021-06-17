<?php

namespace App\Hierarchy\Schema;

use App\Hierarchy\Schema\Definition\SchemaDefinition;

class HierarchyNormalizer {
	public function __construct(
		private SchemaDefinition $def, 
		private string $keyId
	) {
	}

	public function getViewName() {
		return sprintf('%s_hierarchy', $this->def->getKeyReflexivityTable($this->keyId));
	}

	public function getSelectStatement() {
		if($this->def->isKeyOrdered($this->keyId)) {

			$sqlOrderSelect = "\n self.%s AS %s,";
			$sqlOrderBy = "\n self.%s DESC,";
		}

		if($this->def->isKeyScoped($this->keyId)) {
			return <<<SQL
				SELECT
					scope.id AS %SCOPE%_id,
					parent.id AS parent, 
					%ORDER_SELECT%
					self.id AS id
				FROM
					%NAME% self
					INNER JOIN %SCOPE% scope
					ON scope.id = self.%SCOPE%_id
					INNER JOIN %NAME%_closure reflexive
					ON reflexive.depth = 0
					AND reflexive.child_id = self.id
					AND reflexive.parent_id = self.id
					LEFT JOIN %NAME%_closure closure
					ON closure.child_id = self.id AND closure.depth = 1
					LEFT JOIN %NAME% parent
					ON parent.id = closure.parent_id
				ORDER BY
				    parent.id ASC, 
				    %ORDER_BY%
					self.id ASC
			SQL;
		} else {
			return <<<SQL
				SELECT
					parent.id AS parent, 
					%ORDER_SELECT%
					self.id AS id
				FROM
					%NAME% self
					INNER JOIN %NAME%_closure reflexive
					ON reflexive.depth = 0
					AND reflexive.child_id = self.id
					AND reflexive.parent_id = self.id
					LEFT JOIN %NAME%_closure closure
					ON closure.child_id = self.id AND closure.depth = 1
					LEFT JOIN %NAME% parent
					ON parent.id = closure.parent_id
				ORDER BY
				    parent.id ASC, %ORDER_SELECT%
					self.id ASC
			SQL;
		}
	}
}