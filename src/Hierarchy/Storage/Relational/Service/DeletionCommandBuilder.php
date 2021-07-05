<?php

namespace App\Hierarchy\Storage\Relational\Service;

use App\Hierarchy\Storage\Relational\Naming;
use App\Hierarchy\Storage\Relational\ColumnCoder;
use App\Hierarchy\Schema\Definition\SchemaDefinition;

class DeletionCommandBuilder  {

	public function __construct(private SchemaDefinition $schemaDef, private Naming $naming, private ColumnCoder $coder) {
	}

	// getCommandForDeleteMultipleNodesClosure
	// getCommandForDeleteMultipleNodes
	// getSelectForCollectChildByIdReflexive
	// getSelectForCollectSelfById
	// getSelectForCollectChildByScopeReflexive
	// getSelectForCollectChildByScope
	// getSelectForReferencedNodes
}