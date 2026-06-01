<?php

namespace App\Twig;

use App\Form\Type\CreateChildNodeType;
use App\Form\Type\CreateNodeType;
use App\Form\Type\DeleteNodeType;
use App\Form\Type\EditNodeType;
use App\Hierarchy\Data\Node;
use App\Hierarchy\Schema\RecursiveLoader;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class HierarchyExtension extends AbstractExtension
{
    public function __construct(private FormFactoryInterface $formFactory, private UrlGeneratorInterface $urlGen, private RecursiveLoader $schemaLoader)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('creationForm', [$this, 'buildCreationForm']),
            new TwigFunction('childCreationForm', [$this, 'buildChildCreationForm']),
            new TwigFunction('editForm', [$this, 'buildEditForm']),
            new TwigFunction('deletionForm', [$this, 'buildDeletionForm']),
        ];
    }

    public function buildCreationForm($hierarchy, $keyId): FormView
    {
        $creationForm = $this->formFactory->create(
            CreateNodeType::class,
            [],
            [
                'key' => $hierarchy->getKey($keyId),
                'hierarchySlug' => $hierarchy->getSlug(),
                'storageConnection' => $this->schemaLoader->loadStorageConnection($hierarchy->getSlug()),
                'action' => $this->urlGen->generate('create_node', [
                    'hierarchySlug' => $hierarchy->getSlug(),
                    'keyId' => $keyId,
                ]),
                'method' => 'POST',
            ]
        );

        return $creationForm->createView();
    }

    public function buildChildCreationForm($hierarchy, $keyId, $nodeId, $childKeyId): FormView
    {
        $creationForm = $this->formFactory->create(
            CreateChildNodeType::class,
            [],
            [
                'key' => $hierarchy->getKey($childKeyId),
                'hierarchySlug' => $hierarchy->getSlug(),
                'storageConnection' => $this->schemaLoader->loadStorageConnection($hierarchy->getSlug()),
                'action' => $this->urlGen->generate('create_child_node', [
                    'hierarchySlug' => $hierarchy->getSlug(),
                    'keyId' => $keyId,
                    'nodeId' => $nodeId,
                    'childKeyId' => $childKeyId,
                ]),
                'method' => 'POST',
            ]
        );

        return $creationForm->createView();
    }

    public function buildEditForm($hierarchy, Node $node): FormView
    {
        $key = $hierarchy->getKey($node->getKey());
        $editForm = $this->formFactory->create(
            EditNodeType::class,
            [
                'fields' => $key->getNodeFieldValues($node),
            ],
            [
                'key' => $key,
                'hierarchySlug' => $hierarchy->getSlug(),
                'nodeId' => $node->getId(),
                'storageConnection' => $this->schemaLoader->loadStorageConnection($hierarchy->getSlug()),
                'action' => $this->urlGen->generate('update_node', [
                    'hierarchySlug' => $hierarchy->getSlug(),
                    'keyId' => $node->getKey(),
                    'nodeId' => $node->getId(),
                ]),
                'method' => 'POST',
            ]
        );

        return $editForm->createView();
    }

    public function buildDeletionForm($hierarchy, $keyId, $nodeId): FormView
    {
        $deletionForm = $this->formFactory->create(
            DeleteNodeType::class,
            [
                'cascade' => 'no',
            ],
            [
                'key' => $hierarchy->getKey($keyId),
                'storageConnection' => $this->schemaLoader->loadStorageConnection($hierarchy->getSlug()),
                'action' => $this->urlGen->generate('delete_node', [
                    'hierarchySlug' => $hierarchy->getSlug(),
                    'keyId' => $keyId,
                    'nodeId' => $nodeId,
                ]),
                'method' => 'POST',
            ]
        );

        return $deletionForm->createView();
    }
}
