<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

class View {
	public function __construct(
		private string $name,
		private string $query
	) {

	}
}