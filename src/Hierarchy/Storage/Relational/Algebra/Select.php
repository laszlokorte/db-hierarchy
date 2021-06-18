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
		private ?array $grouping = NULL,
		private ?ValueInterface $having = NULL
	) {

	}
}