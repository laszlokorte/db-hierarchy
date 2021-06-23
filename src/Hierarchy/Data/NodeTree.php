<?php

namespace App\Hierarchy\Data;

class NodeTree {
	public function __construct(private string $keyId, private array $rows, private ?string $scopeId = NULL, private ?string $parentId = NULL) {
		
	}
}