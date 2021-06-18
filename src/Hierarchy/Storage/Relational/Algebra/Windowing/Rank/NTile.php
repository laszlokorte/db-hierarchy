<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Windowing\Rank;

use App\Hierarchy\Storage\Relational\Algebra\Windowing\RankWindowFunction;

class NTile implements RankWindowFunction {
	public function __construct(private int $buckets) {
	}

	public function getBuckets() {
		return $this->buckets;
	}
}