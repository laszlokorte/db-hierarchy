<?php

namespace App\Request\ParamConverter;

use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;

use App\Hierarchy\Storage\Relational\StorageConnection;
use App\Hierarchy\Schema\Hierarchy;
use App\Hierarchy\Schema\RecursiveLoader;

class HierarchyParamConverter implements ParamConverterInterface {

	public function __construct(private RecursiveLoader $schemaLoader) {

	}

	function apply(Request $request, ParamConverter $configuration) {
		if($configuration->getClass() === StorageConnection::class) {
			$name = $configuration->getName();
			$object = $this->schemaLoader->loadStorageConnection($request->attributes->get('hierarchySlug', 'system'));

			$request->attributes->set($name, $object);
		} elseif($configuration->getClass() === Hierarchy::class) {
			$name = $configuration->getName();
			$object = $this->schemaLoader->loadSchema($request->attributes->get('hierarchySlug', 'system'));

			$request->attributes->set($name, $object);
		}
	}

	function supports(ParamConverter $configuration) {
		if($configuration->getClass() === StorageConnection::class) {
			return true;
		} elseif($configuration->getClass() === Hierarchy::class) {
			return true;
		} else {
			return false;
		}
	}
}