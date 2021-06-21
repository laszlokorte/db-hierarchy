<?php

namespace App\Hierarchy\Storage\Relational\Dialect;

use App\Hierarchy\Storage\Relational\Algebra;
use App\Hierarchy\Storage\Relational\Algebra\Select;
use App\Hierarchy\Storage\Relational\Algebra\Insert;
use App\Hierarchy\Storage\Relational\Algebra\Update;
use App\Hierarchy\Storage\Relational\Algebra\Delete;
use App\Hierarchy\Storage\Relational\Algebra\CreateView;
use App\Hierarchy\Storage\Relational\Algebra\CreateTable;
use App\Hierarchy\Storage\Relational\Algebra\Value\Parameter;

interface DialectInterface {
	public function selectToString(Select $select);

	public function insertToString(Insert $insert);

	public function updateToString(Update $update);

	public function deleteToString(Delete $delete);

	public function createViewToString(CreateView $createView);

	public function createTableToString(CreateTable $createView);

	public function dropViewToString(CreateView $createView);

	public function dropTableToString(CreateTable $createView);

	public function parameterToString(Parameter $param);
}