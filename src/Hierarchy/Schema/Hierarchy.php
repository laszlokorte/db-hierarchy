<?php

namespace App\Hierarchy\Schema;

use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Schema\Table\NodeTable;
use App\Hierarchy\Schema\Table\ClosureTable;
use App\Hierarchy\Schema\View\HierarchyView;
use App\Hierarchy\Schema\View\ClosureFindInvalidsView;
use App\Hierarchy\Schema\View\ClosureFindMissingsView;
use App\Hierarchy\Schema\View\HierarchyNormalizedOrderView;

class Hierarchy {
	public function __construct(
		private SchemaDefinition $def, 
		private string $keyId
	) {
		$this->hierarchyView = new HierarchyView($this->def, $this->keyId);
		$this->nodeTable = new NodeTable($this->def, $this->keyId);
	}

	public function getTables() {
		$tables = [
			$this->nodeTable,
		];

		if($this->def->isKeyReflexive($this->keyId)) {
			$tables[] = new ClosureTable($this->def, $this->keyId);
		}

		return $tables;
	}

	public function getViews() {
		$views = [
			$this->hierarchyView
		];

		if($this->def->isKeyReflexive($this->keyId)) {
			$views[] = new ClosureFindInvalidsView($this->def, $this->keyId);
			$views[] = new ClosureFindMissingsView($this->def, $this->keyId);
		}

		if($this->def->isKeyOrdered($this->keyId)) {
			$views[] = new HierarchyNormalizedOrderView($this->hierarchyView);
		}

		return $views;
	}

}