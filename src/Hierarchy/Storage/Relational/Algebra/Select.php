<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;

class Select {
	public function __construct(
		private array $projections,
		private array $tableNames = [],
		private array $joins = [],
		private ?ValueInterface $condition = NULL,
		private array $orders = [],
		private ?int $limit = NULL,
		private int $offset = 0,
		private ?array $groupings = NULL,
		private ?ValueInterface $having = NULL,
		private array $unions = []
	) {
	}

	public function getProjections() {
		return $this->projections;
	}

	public function getTables() {
		return $this->tableNames;
	}

	public function getJoins() {
		return $this->joins;
	}

	public function getCondition() {
		return $this->condition;
	}

	public function getOrders() {
		return $this->orders;
	}

	public function getLimit() {
		return $this->limit;
	}

	public function getOffset() {
		return $this->offset;
	}

	public function getGroupings() {
		return $this->groupings;
	}

	public function getHaving() {
		return $this->having;
	}

	public function getUnions() {
		return $this->unions;
	}
}