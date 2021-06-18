<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Windowing;

use App\Hierarchy\Storage\Relational\Algebra\Aggregation\AggregationInterface;
use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;

class RankWindow implements WindowingInterface {
	public function __construct(
		private RankWindowFunction $rank
	) {
	}
	
	public function getRank() {
		return $this->rank;
	}
}