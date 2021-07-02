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
				$schema = $this->schemaLoader->loadSchema($request->attributes->get('hierarchySlug', 'system'));

				$request->attributes->set($name, $schema);
			} elseif($configuration->getClass() === Key::class) {
				$schemaSlug = $request->attributes->get('hierarchySlug', 'system');
				$keyId = $request->attributes->get($name.'Id');
				$schema = $this->schemaLoader->loadSchema($schemaSlug);

				if(empty($keyId) || !$schema->hasKey($keyId)) {
					throw new NotFoundHttpException('Key not defined:'.$keyId);
				}

				$request->attributes->set($name, $schema->getKey($keyId));
			} elseif($configuration->getClass() === Field::class) {
				$schemaSlug = $request->attributes->get('hierarchySlug', 'system');
				$keyId = $request->attributes->get('keyId');
				$fieldId = $request->attributes->get($name.'Id');
				$schema = $this->schemaLoader->loadSchema($schemaSlug);

				if(empty($keyId) || !$schema->hasKey($keyId)) {
					throw new NotFoundHttpException('Key not defined');
				}

				$key = $schema->getKey($keyId);

				if(empty($fieldId) || !$key->hasField($fieldId)) {
					throw new NotFoundHttpException('Field not defined');
				}

				$request->attributes->set($name, $key->getField($fieldId));
			} 
		} catch(\Exxception $e) {
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