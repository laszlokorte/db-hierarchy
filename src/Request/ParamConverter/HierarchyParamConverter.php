<?php

namespace App\Request\ParamConverter;

use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use App\Hierarchy\Storage\Relational\StorageConnection;
use App\Hierarchy\Schema\Hierarchy;
use App\Hierarchy\Schema\Key;
use App\Hierarchy\Schema\Field;
use App\Hierarchy\Schema\RecursiveLoader;

class HierarchyParamConverter implements ParamConverterInterface {

	public function __construct(private RecursiveLoader $schemaLoader) {

	}

	function apply(Request $request, ParamConverter $configuration) {
		try {
			$name = $configuration->getName();

			if($configuration->getClass() === StorageConnection::class) {
				$object = $this->schemaLoader->loadStorageConnection($request->attributes->get('hierarchySlug', 'system'));

				$request->attributes->set($name, $object);
			} elseif($configuration->getClass() === Hierarchy::class) {
				$name = $configuration->getName();
				$object = $this->schemaLoader->loadSchema($request->attributes->get('hierarchySlug', 'system'));

				$request->attributes->set($name, $object);
			} elseif($configuration->getClass() === Key::class) {
				$object = $this->schemaLoader->loadSchema($request->attributes->get('hierarchySlug', 'system'))->getKey($request->attributes->get($name.'Id'));

				$request->attributes->set($name, $object);
			} elseif($configuration->getClass() === Field::class) {
				$object = $this->schemaLoader->loadSchema($request->attributes->get('hierarchySlug', 'system'))->getKey($request->attributes->get($name.'Id'))->getField($request->attributes->get($name.'Id'));

				$request->attributes->set($name, $object);
			} 
		} catch(\Exception $e) {
			throw new NotFoundHttpException('Hierarchy not defined');
		}
	}

	function supports(ParamConverter $configuration) {
		if($configuration->getClass() === StorageConnection::class) {
			return true;
		} elseif($configuration->getClass() === Hierarchy::class) {
			return true;
		} elseif($configuration->getClass() === Key::class) {
			return true;
		} elseif($configuration->getClass() === Field::class) {
			return true;
		} 

		return false;
	}
}