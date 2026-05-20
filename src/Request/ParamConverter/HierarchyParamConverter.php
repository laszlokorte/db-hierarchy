<?php

namespace App\Request\ParamConverter;

use App\Hierarchy\Schema\Field;
use App\Hierarchy\Schema\Hierarchy;
use App\Hierarchy\Schema\Key;
use App\Hierarchy\Schema\RecursiveLoader;
use App\Hierarchy\Storage\Relational\StorageConnection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class HierarchyParamConverter implements ValueResolverInterface
{
    public function __construct(private RecursiveLoader $schemaLoader)
    {
    }

    public function resolve(Request $request, ArgumentMetadata $configuration): \Generator
    {
        try {
            $name = $configuration->getName();
            $options = []; // $configuration->getOptions();

            if (StorageConnection::class === $configuration->getType()) {
                $object = $this->schemaLoader->loadStorageConnection($request->attributes->get($options['slug'] ?? 'hierarchySlug'));

                yield $object;
            } elseif (Hierarchy::class === $configuration->getType()) {
                $name = $configuration->getName();
                $schema = $this->schemaLoader->loadSchema($request->attributes->get($name.'Slug', 'system'));

                yield $schema;
            } elseif (Key::class === $configuration->getType()) {
                $schemaSlug = $request->attributes->get($options['slug'] ?? 'hierarchySlug', 'system');
                $keyId = $request->attributes->get($name.'Id');
                $schema = $this->schemaLoader->loadSchema($schemaSlug);

                if (empty($keyId) || !$schema->hasKey($keyId)) {
                    throw new NotFoundHttpException('Key not defined:'.$keyId);
                }

                yield $schema->getKey($keyId);
            } elseif (Field::class === $configuration->getType()) {
                $schemaSlug = $request->attributes->get($options['slug'] ?? 'hierarchySlug', 'system');
                $keyId = $request->attributes->get('keyId');
                $fieldId = $request->attributes->get($name.'Id');
                $schema = $this->schemaLoader->loadSchema($schemaSlug);

                if (empty($keyId) || !$schema->hasKey($keyId)) {
                    throw new NotFoundHttpException('Key not defined');
                }

                $key = $schema->getKey($keyId);

                if (empty($fieldId) || !$key->hasField($fieldId)) {
                    throw new NotFoundHttpException('Field not defined');
                }

                yield $key->getField($fieldId);
            }
        } catch (\Exception $e) {
            throw new NotFoundHttpException('Hierarchy not defined');
        }
    }
}
