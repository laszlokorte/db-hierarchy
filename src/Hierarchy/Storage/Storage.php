<?php

namespace App\Hierarchy\Storage;

class Storage {
	public function __construct(private StorageSchemaInterface $schema, private StorageReaderInterface $reader, private StorageReaderInterface $writer) {

	}
}