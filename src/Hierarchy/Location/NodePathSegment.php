<?php

namespace App\Hierarchy\Location;

class NodePathSegment {
	public function __construct(
		private string $keyId,
		private array $nodeIds
	) {
	}
}