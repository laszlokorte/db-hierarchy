<?php

namespace App\Hierarchy\Storage\Relational\Adapter;

use App\Hierarchy\Storage\Relational\Algebra;
use App\Hierarchy\Storage\Relational\Algebra\Select;
use App\Hierarchy\Storage\Relational\Algebra\Insert;
use App\Hierarchy\Storage\Relational\Algebra\Update;
use App\Hierarchy\Storage\Relational\Algebra\Delete;
use App\Hierarchy\Storage\Relational\Algebra\CreateView;
use App\Hierarchy\Storage\Relational\Algebra\CreateTable;

interface AdapterInterface {
	public function selectToString(Select $select);

	public function insertToString(Insert $insert);

	public function updateToString(Update $update);

	public function deleteToString(Delete $delete);

	public function createViewToString(CreateView $createView);

	public function createTableToString(CreateTable $createView);

	public function dropViewToString(CreateView $createView);

	public function dropTableToString(CreateTable $createView);

}