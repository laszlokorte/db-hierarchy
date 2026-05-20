<?php

namespace App\Hierarchy\Storage\Relational\Dialect;

use App\Hierarchy\Storage\Relational\Algebra\CreateTable;
use App\Hierarchy\Storage\Relational\Algebra\CreateView;
use App\Hierarchy\Storage\Relational\Algebra\Delete;
use App\Hierarchy\Storage\Relational\Algebra\Insert;
use App\Hierarchy\Storage\Relational\Algebra\Select;
use App\Hierarchy\Storage\Relational\Algebra\Update;
use App\Hierarchy\Storage\Relational\Algebra\Value\Parameter;

interface DialectInterface
{
    public function selectToString(Select $select): string;

    public function insertToString(Insert $insert): string;

    public function updateToString(Update $update): string;

    public function deleteToString(Delete $delete): string;

    public function createViewToString(CreateView $createView): string;

    public function createTableToString(CreateTable $createTable): string;

    public function addForeignKeysTableToString(CreateTable $createTable): string;

    public function dropViewToString(CreateView $createView): string;

    public function dropTableToString(CreateTable $createView): string;

    public function parameterToString(Parameter $param): string;

    public function stringQueryViewNames(): string;

    public function stringQueryTableNames(): string;

    public function stringSwitchForeignKey(bool $on): ?string;
}
