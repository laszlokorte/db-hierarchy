<?php

namespace App\Hierarchy\Schema;

class KeyDefinition {
	private $label = NULL;

	private $idColumn;
	private $scope;
	private $reflexive;
	private $order;
	private $fields;
	private $templateString;

	public function __construct($scope, $reflexive, $order, $fields) {
		$this->scope = $scope;
		$this->reflexive = $reflexive;
		$this->order = new OrderDefinition($order, true);
		$this->fields = $fields;
	}
}