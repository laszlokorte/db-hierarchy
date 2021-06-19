<?php

namespace App\Hierarchy\Storage\Relational;

class CommandBuilder  {

	public function __construct(private SchemaDefinition $schemaDef, private Naming $naming) {
	}

	public function getCommandForCreateNode(string $keyId) {
		
	}

	public function getCommandForUpdateNode(string $keyId) {
		
	}

	public function getCommandForDeleteNode(string $keyId) {
		
	}

	public function getCommandForMoveNode(string $keyId) {
		
	}
}