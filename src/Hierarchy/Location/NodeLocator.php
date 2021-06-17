<?php

namespace App\Hierarchy\Location;

class NodeLocator {
	public function __construct(
		private string $keyId,
		private string $nodeId
	) {
	}
}