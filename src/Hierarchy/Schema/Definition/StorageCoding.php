<?php

namespace App\Hierarchy\Schema\Definition;

use App\Hierarchy\Schema\Definition\StorageCodingType;

class StorageCoding {
	public function __construct(
		private string $type, 
		private ?string $parameter = NULL
	) {
	}

	public function getType() {
		return $this->type;
	}

	public function getParameter() {
		return $this->parameter;
	}
}