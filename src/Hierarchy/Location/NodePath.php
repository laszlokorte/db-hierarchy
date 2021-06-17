<?php

namespace App\Hierarchy\Location;

class NodePath {
	public function __construct(
		private array $parentSegments,
		private NodeLocator $self,
		private string $trailing
	) {
	}
}