<?php

namespace App\Hierarchy\Schema;

use App\Hierarchy\Schema\Definition\SchemaDefinition;

class OrderNormalizer {
	public function __construct(
		private SchemaDefinition $def, 
		private string $keyId
	) {
	}

	public function getViewName() {
		return sprintf('%s_normalized_order', $this->def->getKeyTable($this->keyId));
	}

	public function getSelectStatement() {
		if($this->def->isKeyScoped($this->keyId)) {
			if($this->def->isKeyReflexive($this->keyId)) {
				return <<<SQL
					SELECT 
						s.id AS id,
						s.'%ORDER%' AS stored_order,
						h.parent AS parent,
						h.%SCOPE%_id AS scope,
						ROW_NUMBER() OVER(
							PARTITION BY h.parent, h.%SCOPE%_id 
							ORDER BY h.%ORDER% ASC, h.id DESC
						) AS normalized_order 
					FROM %NAME%_hierarchy h 
					INNER JOIN %NAME% s ON s.id=h.id
				SQL;
			} else {
				return <<<SQL
					SELECT 
						s.id AS id,
						s.'%ORDER%' AS stored_order,
						s.'%SCOPE%_id' AS scope,
						ROW_NUMBER() OVER(
							PARTITION BY %SCOPE%_id 
							ORDER BY '%ORDER%' ASC, id DESC
						) AS normalized_order 
					FROM %NAME% s
				SQL;
			}
		} else {
			if($reflexive) {
				return <<<SQL
					SELECT 
						s.id AS id,
						s.'%ORDER%' AS stored_order,
						h.parent AS parent,
						ROW_NUMBER() OVER(
							PARTITION BY parent 
							ORDER BY s.'%ORDER%' ASC, s.id DESC
						) AS normalized_order 
					FROM %NAME%_hierarchy h 
					INNER JOIN %NAME% s ON h.id=s.id
				SQL;
			} else {
				return <<<SQL
					SELECT 
						s.id AS id,
						s.'%ORDER%' AS stored_order,
						ROW_NUMBER() OVER(
							ORDER BY '%ORDER%' ASC, id DESC
						) AS normalized_order 
					FROM %NAME% s
				SQL;
			}
		}
	}
}