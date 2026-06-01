<?php

namespace App\Controller;

use App\Form\Type\CreateChildNodeType;
use App\Form\Type\CreateNodeType;
use App\Form\Type\DeleteNodeType;
use App\Form\Type\EditNodeFieldType;
use App\Form\Type\EditNodeType;
use App\Form\Type\MoveNodeType;
use App\Form\Type\OrderNodeType;
use App\Form\Type\System\InstallType;
use App\Form\Type\System\RepairAllType;
use App\Form\Type\System\RepairType;
use App\Form\Type\System\UninstallType;
use App\Hierarchy\Data\MultiCollection;
use App\Hierarchy\Schema\Field;
use App\Hierarchy\Schema\Hierarchy;
use App\Hierarchy\Schema\Key;
use App\Hierarchy\Storage\Relational\Exception\DeletionBlockedException;
use App\Hierarchy\Storage\Relational\StorageConnection;
use App\Request\ParamConverter\HierarchySlug;
use App\Response\RedirectHandler;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class HierarchyController
{
    /**
     * @return array<string,mixed>|RedirectResponse
     */
    #[Route('/{hierarchySlug}', name: 'hierarchy_root', methods: 'GET', defaults: ['hierarchySlug' => 'system'])]
    #[Template('hierarchy/root.html.twig')]
    public function root(Session $session, UrlGeneratorInterface $urlGen, Hierarchy $hierarchy, StorageConnection $storageConnection, RedirectHandler $redirectHandler): array|RedirectResponse
    {
        try {
            return [
                'hierarchy' => $hierarchy,
                'rootNodes' => $storageConnection->getQueryService()->findAllRootNodes(),
            ];
        } catch (\Doctrine\DBAL\Exception\DriverException) {
            $session->getFlashBag()->add('error', 'An error occured. Maybe the schema has to be updated');

            return new RedirectResponse($urlGen->generate('show_system_installer', ['hierarchySlug' => 'system', 'subHierarchySlug' => $hierarchy->getSlug()]));
        }
    }

    /**
     * @return array<string,mixed>
     */
    #[Route('/{hierarchySlug}/_full-tree', name: 'hierarchy_tree', methods: 'GET')]
    #[Template('hierarchy/tree.html.twig')]
    public function tree(Hierarchy $hierarchy, StorageConnection $storageConnection): array
    {
        return [
            'hierarchy' => $hierarchy,
            'hierarchyNodes' => $storageConnection->getQueryService()->findAllHierarchyNodes(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    #[Route('/{hierarchySlug}/_setup/{subHierarchySlug}', name: 'show_system_installer', methods: 'GET', defaults: ['subHierarchySlug' => 'system'], requirements: ['hierarchySlug' => 'system'])]
    // #[ParamConverter('storageConnection', options: ['slug' => 'subHierarchySlug'])]
    #[Template('hierarchy/show_installer.html.twig')]
    public function showInstaller(FormFactoryInterface $formFactory, UrlGeneratorInterface $urlGen, Hierarchy $hierarchy, Hierarchy $subHierarchy, #[HierarchySlug('subHierarchySlug')] StorageConnection $storageConnection): array
    {
        $installForm = $formFactory->create(InstallType::class, [
        ], [
            'action' => $urlGen->generate('system_install', [
                'hierarchySlug' => $hierarchy->getSlug(),
                'subHierarchySlug' => $subHierarchy->getSlug(),
            ]),
            'method' => 'POST',
        ]);
        $uninstallForm = $formFactory->create(UninstallType::class, [], [
            'action' => $urlGen->generate('system_uninstall', [
                'hierarchySlug' => $hierarchy->getSlug(),
                'subHierarchySlug' => $subHierarchy->getSlug(),
            ]),
        ]);
        $installViewsForm = $formFactory->create(InstallType::class, [
            'only_views' => true,
        ], [
            'action' => $urlGen->generate('system_install', [
                'hierarchySlug' => $hierarchy->getSlug(),
                'subHierarchySlug' => $subHierarchy->getSlug(),
            ]),
            'method' => 'POST',
        ]);

        return [
            'hierarchy' => $hierarchy,
            'subHierarchy' => $subHierarchy,
            'installer' => $storageConnection->getInstallationService(),
            'installForm' => $installForm->createView(),
            'installViewsForm' => $installViewsForm->createView(),
            'uninstallForm' => $uninstallForm->createView(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    #[Route('/{hierarchySlug}/_setup/{subHierarchySlug}/table/{tableName}', name: 'show_system_installer_table', methods: 'GET', defaults: ['subHierarchySlug' => 'system'], requirements: ['hierarchySlug' => 'system'])]
    // #[ParamConverter('storageConnection', options: ['slug' => 'subHierarchySlug'])]
    #[Template('hierarchy/show_installer_table.html.twig')]
    public function showInstallerTable(FormFactoryInterface $formFactory, UrlGeneratorInterface $urlGen, Hierarchy $hierarchy, Hierarchy $subHierarchy, string $tableName, #[HierarchySlug('subHierarchySlug')] StorageConnection $storageConnection): array
    {
        return [
            'hierarchy' => $hierarchy,
            'subHierarchy' => $subHierarchy,
            'installer' => $storageConnection->getInstallationService(),
            'tableName' => $tableName,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    #[Route('/{hierarchySlug}/_setup/{subHierarchySlug}/view/{viewName}', name: 'show_system_installer_view', methods: 'GET', defaults: ['subHierarchySlug' => 'system'], requirements: ['hierarchySlug' => 'system'])]
    // #[ParamConverter('storageConnection', options: ['slug' => 'subHierarchySlug'])]
    #[Template('hierarchy/show_installer_view.html.twig')]
    public function showInstallerView(FormFactoryInterface $formFactory, UrlGeneratorInterface $urlGen, Hierarchy $hierarchy, Hierarchy $subHierarchy, string $viewName, #[HierarchySlug('subHierarchySlug')] StorageConnection $storageConnection): array
    {
        return [
            'hierarchy' => $hierarchy,
            'subHierarchy' => $subHierarchy,
            'installer' => $storageConnection->getInstallationService(),
            'viewName' => $viewName,
        ];
    }

    #[Route('/{hierarchySlug}/_setup/{subHierarchySlug}', name: 'system_install', methods: 'POST', requirements: ['hierarchySlug' => 'system'])]
    // #[ParamConverter('storageConnection', options: ['slug' => 'subHierarchySlug'])]
    public function install(Request $request, Session $session, FormFactoryInterface $formFactory, UrlGeneratorInterface $urlGen, Hierarchy $hierarchy, Hierarchy $subHierarchy, #[HierarchySlug('subHierarchySlug')] StorageConnection $storageConnection, RedirectHandler $redirectHandler): RedirectResponse
    {
        $installForm = $formFactory->create(InstallType::class, [], [
            'action' => $urlGen->generate('system_install', [
                'hierarchySlug' => $hierarchy->getSlug(),
                'subHierarchySlug' => $subHierarchy->getSlug(),
            ]),
            'method' => 'POST',
        ]);

        $installForm->handleRequest($request);

        if ($installForm->isSubmitted() && $installForm->isValid()) {
            $viewsOnly = (bool) $installForm->getData()['only_views'];

            $storageConnection->getInstallationService()->createSchema(true, $viewsOnly);

            $session->getFlashBag()->add('success', 'Schema has been updated.');
        }

        return new RedirectResponse($urlGen->generate('show_system_installer', ['hierarchySlug' => $hierarchy->getSlug(), 'subHierarchySlug' => $subHierarchy->getSlug()]));
    }

    #[Route('/{hierarchySlug}/_uninstall/{subHierarchySlug}', name: 'system_uninstall', methods: 'POST', requirements: ['hierarchySlug' => 'system'])]
    // #[ParamConverter('storageConnection', options: ['slug' => 'subHierarchySlug'])]
    public function uninstall(Request $request, Session $session, FormFactoryInterface $formFactory, UrlGeneratorInterface $urlGen, Hierarchy $hierarchy, Hierarchy $subHierarchy, #[HierarchySlug('subHierarchySlug')] StorageConnection $storageConnection, RedirectHandler $redirectHandler): RedirectResponse
    {
        $uninstallForm = $formFactory->create(UninstallType::class, [], [
            'action' => $urlGen->generate('system_uninstall', [
                'hierarchySlug' => $hierarchy->getSlug(),
                'subHierarchySlug' => $subHierarchy->getSlug(),
            ]),
        ]);

        $uninstallForm->handleRequest($request);

        if ($uninstallForm->isSubmitted() && $uninstallForm->isValid()) {
            $storageConnection->getInstallationService()->dropSchema();

            $session->getFlashBag()->add('success', 'Schema has been removed.');
        }

        return new RedirectResponse($urlGen->generate('show_system_installer', ['hierarchySlug' => $hierarchy->getSlug(), 'subHierarchySlug' => $subHierarchy->getSlug()]));
    }

    /**
     * @return array<string,mixed>
     */
    #[Route('/{hierarchySlug}/_health/{subHierarchySlug}', name: 'show_health', methods: 'GET', defaults: ['subHierarchySlug' => 'system'], requirements: ['hierarchySlug' => 'system'])]
    // #[ParamConverter('storageConnection', options: ['slug' => 'subHierarchySlug'])]
    #[Template('hierarchy/show_health.html.twig')]
    public function showHealth(FormFactoryInterface $formFactory, UrlGeneratorInterface $urlGen, Hierarchy $hierarchy, Hierarchy $subHierarchy, #[HierarchySlug('subHierarchySlug')] StorageConnection $storageConnection): array
    {
        $health = $storageConnection->getRepairService()->findAllDefects();

        $repairAllForm = $formFactory->create(RepairAllType::class, [], [
            'action' => $urlGen->generate('repair', [
                'hierarchySlug' => $hierarchy->getSlug(),
                'subHierarchySlug' => $subHierarchy->getSlug(),
            ]),
        ]);

        $repairForms = array_combine(
            array_map(fn ($diagnostic) => $diagnostic->getKeyId(), $health),
            array_map(fn ($diagnostic) => $formFactory->create(RepairType::class, [], [
                'action' => $urlGen->generate('repair_key', [
                    'hierarchySlug' => $hierarchy->getSlug(),
                    'subHierarchySlug' => $subHierarchy->getSlug(),
                    'keyId' => $diagnostic->getKeyId(),
                ]),
            ]),
                $health
            )
        );

        return [
            'hierarchy' => $hierarchy,
            'subHierarchy' => $subHierarchy,
            'health' => $health,
            'repairAllForm' => $repairAllForm->createView(),
            'repairForms' => array_map(fn ($f) => $f->createView(), $repairForms),
        ];
    }

    #[Route('/{hierarchySlug}/_repair/{subHierarchySlug}', name: 'repair', methods: 'POST', requirements: ['hierarchySlug' => 'system'])]
    // #[ParamConverter('storageConnection', options: ['slug' => 'subHierarchySlug'])]
    public function repairAllDefects(Request $request, FormFactoryInterface $formFactory, UrlGeneratorInterface $urlGen, Session $session, Hierarchy $hierarchy, Hierarchy $subHierarchy, #[HierarchySlug('subHierarchySlug')] StorageConnection $storageConnection, RedirectHandler $redirectHandler): RedirectResponse
    {
        $repairAllForm = $formFactory->create(RepairAllType::class, [], [
            'action' => $urlGen->generate('repair', [
                'hierarchySlug' => $hierarchy->getSlug(),
                'subHierarchySlug' => $subHierarchy->getSlug(),
            ]),
        ]);

        $repairAllForm->handleRequest($request);

        if ($repairAllForm->isSubmitted() && $repairAllForm->isValid()) {
            $storageConnection->getRepairService()->repairAll();
            $session->getFlashBag()->add('success', 'Full schema has been repaired.');
        }

        return new RedirectResponse($urlGen->generate('show_health', ['hierarchySlug' => $hierarchy->getSlug(), 'subHierarchySlug' => $subHierarchy->getSlug()]));
    }

    #[Route('/{hierarchySlug}/_repair/{subHierarchySlug}/{keyId}', name: 'repair_key', methods: 'POST', requirements: ['hierarchySlug' => 'system'])]
    // #[ParamConverter('storageConnection', options: ['slug' => 'subHierarchySlug'])]
    // #[ParamConverter('key', options: ['slug' => 'subHierarchySlug'])]
    public function repairKeyDefects(Request $request, FormFactoryInterface $formFactory, UrlGeneratorInterface $urlGen, Session $session, Hierarchy $hierarchy, Hierarchy $subHierarchy, #[HierarchySlug('subHierarchySlug')] StorageConnection $storageConnection, #[HierarchySlug('subHierarchySlug')] Key $key, RedirectHandler $redirectHandler): RedirectResponse
    {
        $repairForm = $formFactory->create(RepairType::class, [], [
            'action' => $urlGen->generate('repair_key', [
                'hierarchySlug' => $hierarchy->getSlug(),
                'subHierarchySlug' => $subHierarchy->getSlug(),
                'keyId' => $key->getId(),
            ]),
        ]);

        $repairForm->handleRequest($request);

        if ($repairForm->isSubmitted() && $repairForm->isValid()) {
            $storageConnection->getRepairService()->repairKey($key->getId());
            $session->getFlashBag()->add('success', 'Key has been repaired.');
        }

        return new RedirectResponse($urlGen->generate('show_health', ['hierarchySlug' => $hierarchy->getSlug(), 'subHierarchySlug' => $subHierarchy->getSlug()]));
    }

    #[Route('/{hierarchySlug}/{keyId}({fieldId})/{nodeId}', name: 'show_node_field', methods: 'GET')]
    #[Template('hierarchy/show_node_field.html.twig')]
    public function showNodeField(Hierarchy $hierarchy, StorageConnection $storageConnection, Key $key, string $nodeId, Field $field): JsonResponse
    {
        return new JsonResponse((object) [
            'keyId' => $key->getId(),
            'nodeId' => $nodeId,
            'field' => $field->getId(),
            'value' => $field->readObjectOf(
                $storageConnection->getQueryService()->findNodeField($key->getId(), $nodeId, $field->getId())
            ),
        ]);
    }

    /**
     * @return RedirectResponse|array<string,mixed>
     */
    #[Route('/{hierarchySlug}/{keyId}/{nodeId}/-', name: 'delete_node', methods: 'POST')]
    #[Route('/{hierarchySlug}/{keyId}/{nodeId}/-', name: 'ask_delete_node', methods: 'GET')]
    #[Template('hierarchy/ask_delete_node.html.twig')]
    public function deleteNode(Hierarchy $hierarchy, StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, FormFactoryInterface $formFactory, Session $session, Request $request, Environment $twig, Key $key, string $nodeId, RedirectHandler $redirectHandler): array|RedirectResponse
    {
        $lastParent = $storageConnection->getQueryService()->findNodeDirectParent($key->getId(), $nodeId);

        $deletionService = $storageConnection->getDeletionService();
        $deletion = $deletionService->getDeletionPlan($key->getId(), $nodeId);

        $deletionForm = $formFactory->create(
            DeleteNodeType::class,
            [
                'cascade' => 'yes',
            ],
            [
                'key' => $key,
                'storageConnection' => $storageConnection,
                'action' => $urlGen->generate('delete_node', [
                    'hierarchySlug' => $hierarchy->getSlug(),
                    'keyId' => $key->getId(),
                    'nodeId' => $nodeId,
                ]),
                'method' => 'POST',
            ]
        );

        $deletionForm->handleRequest($request);

        if ($deletionForm->isSubmitted() && 'yes' !== $deletionForm->getData()['cascade'] && $deletion->isCascading()) {
            return new RedirectResponse($urlGen->generate('ask_delete_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'nodeId' => $nodeId]));
        }

        if ($deletionForm->isSubmitted() && $deletionForm->isValid() && $deletion->isNotBlocked()) {
            try {
                $deletionService->performDeletion($deletion);
            } catch (DeletionBlockedException $e) {
                $session->getFlashBag()->add('error', 'Deletion failed');

                return new RedirectResponse($urlGen->generate('ask_delete_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'nodeId' => $nodeId]));
            }
        } else {
            return [
                'hierarchy' => $hierarchy,
                'key' => $key,
                'node' => $storageConnection->getQueryService()->findNode($key->getId(), $nodeId),
                'parentNodes' => $storageConnection->getQueryService()->findParentNodes($key->getId(), $nodeId),
                'deletion' => $deletion,
                'deletionForm' => $deletionForm->createView(),
            ];
        }

        $then = $request->request->get('_then', null);

        $session->getFlashBag()->add('success', 'Node Deleted');

        if ('list' === $then) {
            if ($lastParent) {
                $args = array_merge($lastParent->pathArgs(), ['hierarchySlug' => $hierarchy->getSlug(), 'childKeyId' => $key->getId()]);

                return new RedirectResponse($urlGen->generate('list_child_nodes', $args));
            }

            return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId()]));
        } elseif ('root_list' === $then) {
            return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId()]));
        } elseif ('parent' === $then) {
            if ($lastParent) {
                return new RedirectResponse($urlGen->generate('show_node', array_merge($lastParent->pathArgs(), ['hierarchySlug' => $hierarchy->getSlug()])));
            }

            return new RedirectResponse($urlGen->generate('hierarchy_root', ['hierarchySlug' => $hierarchy->getSlug()]));
        }

        return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId()]));
    }

    /**
     * @return array<string,mixed>|RedirectResponse
     */
    #[Route('/{hierarchySlug}/{keyId}/{nodeId}/_move', name: 'move_node', methods: 'POST')]
    #[Route('/{hierarchySlug}/{keyId}/{nodeId}/_move', name: 'ask_move_node', methods: 'GET')]
    #[Template('hierarchy/ask_move_node.html.twig')]
    public function moveNode(Hierarchy $hierarchy, StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, FormFactoryInterface $formFactory, Session $session, Request $request, Environment $twig, Key $key, string $nodeId, RedirectHandler $redirectHandler): array|RedirectResponse
    {
        if (!$key->isNested()) {
            throw new NotFoundHttpException(sprintf('%s are not nested', $key->getLabel()->getPlural()));
        }

        $node = $storageConnection->getQueryService()->findNode($key->getId(), $nodeId);
        $movementService = $storageConnection->getMovementService();

        $movementForm = $formFactory->create(
            MoveNodeType::class,
            [],
            [
                'key' => $key,
                'nodeId' => $nodeId,
                'nodeNesting' => $node->getNesting(),
                'storageConnection' => $storageConnection,
                'action' => $urlGen->generate('move_node', [
                    'hierarchySlug' => $hierarchy->getSlug(),
                    'keyId' => $key->getId(),
                    'nodeId' => $nodeId,
                ]),
                'method' => 'POST',
            ]
        );

        $movementForm->handleRequest($request);

        if ($movementForm->isSubmitted() && $movementForm->isValid()) {
            list($scope, $parent) = explode('/', $movementForm->getData()['target'], 2);

            $storageConnection->getMovementService()->moveNode($key->getId(), $nodeId, $scope ?: null, $parent ?: null);

            $session->getFlashBag()->add('success', 'Node Moved');
        } else {
            return [
                'hierarchy' => $hierarchy,
                'key' => $key,
                'moveTargets' => $storageConnection->getMovementService()->findNodeMoveTargets($key->getId(), $nodeId),
                'node' => $node,
                'movementForm' => $movementForm->createView(),
                'parentNodes' => $storageConnection->getQueryService()->findParentNodes($key->getId(), $nodeId),
            ];
        }

        $then = $request->request->get('_then', null);

        if ('tree' === $then) {
            return new RedirectResponse($urlGen->generate('hierarchy_tree', ['hierarchySlug' => $hierarchy->getSlug()]));
        }

        return new RedirectResponse($urlGen->generate('ask_move_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'nodeId' => $nodeId]));
    }

    /**
     * @return array<string,mixed>|RedirectResponse
     */
    #[Route('/{hierarchySlug}/{keyId}/{nodeId}/_order', name: 'order_node', methods: 'POST')]
    #[Route('/{hierarchySlug}/{keyId}/{nodeId}/_order', name: 'ask_order_node', methods: 'GET')]
    #[Template('hierarchy/ask_order_node.html.twig')]
    public function orderNode(Hierarchy $hierarchy, StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, FormFactoryInterface $formFactory, Session $session, Request $request, Environment $twig, Key $key, string $nodeId, RedirectHandler $redirectHandler): array|RedirectResponse
    {
        if (!$key->isOrdered()) {
            throw new NotFoundHttpException(sprintf('%s are not ordered', $key->getLabel()->getPlural()));
        }

        $node = $storageConnection->getQueryService()->findNode($key->getId(), $nodeId);
        $orderingService = $storageConnection->getOrderingService();

        $orderForm = $formFactory->create(
            OrderNodeType::class,
            [],
            [
                'key' => $key,
                'nodeId' => $nodeId,
                'storageConnection' => $storageConnection,
                'action' => $urlGen->generate('order_node', [
                    'hierarchySlug' => $hierarchy->getSlug(),
                    'keyId' => $key->getId(),
                    'nodeId' => $nodeId,
                ]),
                'method' => 'POST',
            ]
        );

        $orderForm->handleRequest($request);

        // if($orderForm->isSubmitted()) {
        //     $ordering = $orderingService->getValidatedOrdering($node, $target);

        // } else {
        //     $ordering = $orderingService->getFreshOrdering($node);
        // }

        if ($orderForm->isSubmitted() && $orderForm->isValid()/* && $ordering->isValid() */) {
            $target = $orderForm->getData()['new_position'];

            $storageConnection->getOrderingService()->orderNode($key->getId(), $nodeId, $target);

            $session->getFlashBag()->add('success', 'Node Reordered');
        } else {
            return [
                'hierarchy' => $hierarchy,
                'key' => $key,
                'orderTargets' => $storageConnection->getQueryService()->findNodeSiblings($key->getId(), $nodeId),
                'node' => $node,
                'parentNodes' => $storageConnection->getQueryService()->findParentNodes($key->getId(), $nodeId),
                // 'ordering' => $ordering,
                'orderForm' => $orderForm->createView(),
            ];
        }

        $then = $request->request->get('_then', null);

        if ('tree' === $then) {
            return new RedirectResponse($urlGen->generate('hierarchy_tree', ['hierarchySlug' => $hierarchy->getSlug()]));
        } elseif ('list' === $then) {
            $directParent = $storageConnection->getQueryService()->findNodeDirectParent($key->getId(), $nodeId);

            if ($directParent) {
                return new RedirectResponse($urlGen->generate('list_child_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $directParent->getKey(), 'nodeId' => $directParent->getId(), 'childKeyId' => $key->getId()]));
            }

            return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId()]));
        } elseif ('root_list' === $then) {
            return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId()]));
        } elseif ('parent' === $then) {
            $directParent = $storageConnection->getQueryService()->findNodeDirectParent($key->getId(), $nodeId);

            if ($directParent) {
                return new RedirectResponse($urlGen->generate('show_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $directParent->getKey(), 'nodeId' => $directParent->getId()]));
            }

            return new RedirectResponse($urlGen->generate('hierarchy_root', ['hierarchySlug' => $hierarchy->getSlug()]));
        }

        return new RedirectResponse($urlGen->generate('ask_order_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'nodeId' => $nodeId]));
    }

    /**
     * @return array<string,mixed>|RedirectResponse
     */
    #[Route('/{hierarchySlug}/{keyId}', name: 'create_node', methods: 'POST')]
    #[Route('/{hierarchySlug}/{keyId}/+', name: 'new_root_node', methods: 'GET')]
    #[Template('hierarchy/new_root_node.html.twig')]
    public function createNode(Hierarchy $hierarchy, StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, FormFactoryInterface $formFactory, Session $session, Request $request, Environment $twig, Key $key, RedirectHandler $redirectHandler): array|RedirectResponse
    {
        $creationService = $storageConnection->getCreationService();

        $creationForm = $formFactory->create(
            CreateNodeType::class,
            [],
            [
                'key' => $key,
                'hierarchySlug' => $hierarchy->getSlug(),
                'storageConnection' => $storageConnection,
                'action' => $urlGen->generate('create_node', [
                    'hierarchySlug' => $hierarchy->getSlug(),
                    'keyId' => $key->getId(),
                ]),
                'method' => 'POST',
            ]
        );

        $creationForm->handleRequest($request);

        if ($creationForm->isSubmitted() && $creationForm->isValid()) {
            if ($key->isNested()) {
                list($scope, $parent) = explode('/', $creationForm->getData()['_nesting'], 2);
            } else {
                $scope = null;
                $parent = null;
            }

            $newId = $storageConnection->getCreationService()->createNode($key->getId(), $creationForm->getData()['fields'], $scope, $parent);

            $then = $request->request->get('_then', null);

            $session->getFlashBag()->add('success', 'Node Created');
        } else {
            return [
                'hierarchy' => $hierarchy,
                'key' => $key,
                'parentNodes' => new MultiCollection(null, null, [], null, null),
                'creationForm' => $creationForm->createView(),
            ];
        }

        if ('form' === $then) {
            if ($parent) {
                return new RedirectResponse($urlGen->generate('new_child_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'childKeyId' => $key->getId(), 'nodeId' => $parent]));
            } elseif ($scope) {
                $parentKey = $key->getScopeKey()->getId();

                return new RedirectResponse($urlGen->generate('new_child_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $parentKey, 'childKeyId' => $key->getId(), 'nodeId' => $scope]));
            }

            return new RedirectResponse($urlGen->generate('new_root_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId()]));
        } elseif ('root_form' === $then) {
            return new RedirectResponse($urlGen->generate('new_root_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId()]));
        } elseif ('new' === $then) {
            return new RedirectResponse($urlGen->generate('show_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'nodeId' => $newId]));
        } elseif ('list' === $then) {
            if ($parent) {
                return new RedirectResponse($urlGen->generate('list_child_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'childKeyId' => $key->getId(), 'nodeId' => $parent]));
            } elseif ($scope) {
                $parentKey = $key->getScopeKey()->getId();

                return new RedirectResponse($urlGen->generate('list_child_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $parentKey, 'childKeyId' => $key->getId(), 'nodeId' => $scope]));
            }

            return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId()]));
        } elseif ('root_list' === $then) {
            return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId()]));
        }
        if ($parent) {
            return new RedirectResponse($urlGen->generate('show_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'nodeId' => $parent]));
        } elseif ($scope) {
            $parentKey = $key->getScopeKey()->getId();

            return new RedirectResponse($urlGen->generate('show_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $parentKey, 'nodeId' => $scope]));
        }

        return new RedirectResponse($urlGen->generate('hierarchy_root', ['hierarchySlug' => $hierarchy->getSlug(), 'hierarchySlug' => $hierarchy->getSlug()]));
    }

    /**
     * @return RedirectResponse|array<string,mixed>
     */
    #[Route('/{hierarchySlug}/{keyId}/{nodeId}/{childKeyId}', name: 'create_child_node', methods: 'POST')]
    #[Route('/{hierarchySlug}/{keyId}/{nodeId}/{childKeyId}/+', name: 'new_child_node', methods: 'GET')]
    #[Template('hierarchy/new_child_node.html.twig')]
    public function createChildNode(Hierarchy $hierarchy, StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, FormFactoryInterface $formFactory, Session $session, Request $request, Environment $twig, Key $key, string $nodeId, Key $childKey, RedirectHandler $redirectHandler): array|RedirectResponse
    {
        if ($childKey->isSingleton()) {
            if (!$storageConnection->getQueryService()->findNodeChildren($key->getId(), $nodeId, $childKey->getId())->isEmpty()) {
                return new RedirectResponse($urlGen->generate('list_child_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'nodeId' => $nodeId, 'childKeyId' => $childKey->getId()]));
            }
        }

        $parentNode = $storageConnection->getQueryService()->findNode($key->getId(), $nodeId);

        $creationService = $storageConnection->getCreationService();

        $creationForm = $formFactory->create(
            CreateChildNodeType::class,
            [],
            [
                'key' => $childKey,
                'hierarchySlug' => $hierarchy->getSlug(),
                'storageConnection' => $storageConnection,
                'action' => $urlGen->generate('create_child_node', [
                    'hierarchySlug' => $hierarchy->getSlug(),
                    'keyId' => $key->getId(),
                    'nodeId' => $nodeId,
                    'childKeyId' => $childKey->getId(),
                ]),
                'method' => 'POST',
            ]
        );

        $creationForm->handleRequest($request);

        if ($creationForm->isSubmitted() && $creationForm->isValid()) {
            if ($childKey->getId() == $key->getId()) {
                $scope = $parentNode->getScope();
                $parent = $parentNode->getId();
            } else {
                $scope = $parentNode->getId();
                $parent = null;
            }

            $newId = $storageConnection->getCreationService()->createNode($childKey->getId(), $creationForm->getData()['fields'], $scope, $parent);

            $then = $request->request->get('_then', null);

            $session->getFlashBag()->add('success', 'Node Created');
        } else {
            return [
                'hierarchy' => $hierarchy,
                'key' => $key,
                'childKey' => $childKey,
                'node' => $parentNode,
                'parentNodes' => $storageConnection->getQueryService()->findParentNodes($key->getId(), $nodeId),
                'creationForm' => $creationForm->createView(),
            ];
        }

        if ('form' === $then) {
            if ($parent) {
                return new RedirectResponse($urlGen->generate('new_child_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'childKeyId' => $childKey->getId(), 'nodeId' => $parent]));
            } elseif ($scope) {
                $parentKey = $key->getId();

                return new RedirectResponse($urlGen->generate('new_child_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $parentKey, 'childKeyId' => $childKey->getId(), 'nodeId' => $scope]));
            }

            return new RedirectResponse($urlGen->generate('new_root_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $childKey->getId()]));
        } elseif ('root_form' === $then) {
            return new RedirectResponse($urlGen->generate('new_root_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $childKey->getId()]));
        } elseif ('new' === $then) {
            return new RedirectResponse($urlGen->generate('show_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'nodeId' => $newId]));
        } elseif ('list' === $then) {
            if ($parent) {
                return new RedirectResponse($urlGen->generate('list_child_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'childKeyId' => $key->getId(), 'nodeId' => $parent]));
            } elseif ($scope) {
                $parentKey = $key->getId();

                return new RedirectResponse($urlGen->generate('list_child_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $parentKey, 'childKeyId' => $childKey->getId(), 'nodeId' => $scope]));
            }

            return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId()]));
        } elseif ('root_list' === $then) {
            return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId()]));
        }
        if ($parent) {
            return new RedirectResponse($urlGen->generate('show_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'nodeId' => $parent]));
        } elseif ($scope) {
            $parentKey = $key->getId();

            return new RedirectResponse($urlGen->generate('show_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $parentKey, 'nodeId' => $scope]));
        }

        return new RedirectResponse($urlGen->generate('hierarchy_root', ['hierarchySlug' => $hierarchy->getSlug(), 'hierarchySlug' => $hierarchy->getSlug()]));
    }

    /**
     * @return array<string,mixed>
     */
    #[Route('/{hierarchySlug}/{keyId}/{nodeId}', name: 'show_node', methods: 'GET')]
    #[Template('hierarchy/show_node.html.twig')]
    public function showNode(Hierarchy $hierarchy, StorageConnection $storageConnection, Key $key, string $nodeId): array
    {
        return [
            'hierarchy' => $hierarchy,
            'key' => $key,
            'node' => $storageConnection->getQueryService()->findNode($key->getId(), $nodeId),
            'parentNodes' => $storageConnection->getQueryService()->findParentNodes($key->getId(), $nodeId),
            'childNodes' => $storageConnection->getQueryService()->findNodeAllChildren($key->getId(), $nodeId),
        ];
    }

    /**
     * @return array<string,mixed>|RedirectResponse
     */
    #[Route('/{hierarchySlug}/{keyId}/{nodeId}', name: 'update_node', methods: 'POST')]
    #[Route('/{hierarchySlug}/{keyId}/{nodeId}/_edit', name: 'edit_node', methods: 'GET')]
    #[Template('hierarchy/edit_node.html.twig')]
    public function updateNode(Hierarchy $hierarchy, StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, FormFactoryInterface $formFactory, Session $session, Request $request, Environment $twig, Key $key, string $nodeId, RedirectHandler $redirectHandler): array|RedirectResponse
    {
        $node = $storageConnection->getQueryService()->findNode($key->getId(), $nodeId);
        $updateService = $storageConnection->getUpdateService();

        $editForm = $formFactory->create(
            EditNodeType::class,
            [
                'fields' => $key->getNodeFieldValues($node),
            ],
            [
                'key' => $key,
                'nodeId' => $nodeId,
                'hierarchySlug' => $hierarchy->getSlug(),
                'storageConnection' => $storageConnection,
                'action' => $urlGen->generate('update_node', [
                    'hierarchySlug' => $hierarchy->getSlug(),
                    'keyId' => $key->getId(),
                    'nodeId' => $nodeId,
                ]),
                'method' => 'POST',
            ]
        );

        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $storageConnection->getUpdateService()->updateNode($key->getId(), $nodeId, $editForm->getData()['fields']);

            $then = $request->request->get('_then', null);

            $session->getFlashBag()->add('success', 'Node Updated');
        } else {
            return [
                'hierarchy' => $hierarchy,
                'key' => $key,
                'node' => $node,
                'parentNodes' => $storageConnection->getQueryService()->findParentNodes($key->getId(), $nodeId),
                'editForm' => $editForm->createView(),
            ];
        }

        if ('root' === $then) {
            return new RedirectResponse($urlGen->generate('hierarchy_root', ['hierarchySlug' => $hierarchy->getSlug()]));
        } elseif ('list' === $then) {
            $lastParent = $storageConnection->getQueryService()->findNodeDirectParent($key->getId(), $nodeId);
            if ($lastParent) {
                $args = array_merge($lastParent->pathArgs(), ['hierarchySlug' => $hierarchy->getSlug(), 'childKeyId' => $key->getId()]);

                return new RedirectResponse($urlGen->generate('list_child_nodes', $args));
            }

            return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId()]));
        } elseif ('edit' === $then) {
            return new RedirectResponse($urlGen->generate('edit_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'nodeId' => $nodeId]));
        }

        return new RedirectResponse($urlGen->generate('show_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'nodeId' => $nodeId]));
    }

    /**
     * @return array<string,mixed>|RedirectResponse
     */
    #[Route('/{hierarchySlug}/{keyId}/{nodeId}/_field/{fieldId}', name: 'update_node_field', methods: 'POST')]
    #[Route('/{hierarchySlug}/{keyId}/{nodeId}/_field/{fieldId}', name: 'edit_node_field', methods: 'GET')]
    #[Template('hierarchy/edit_node_field.html.twig')]
    public function updateNodeField(Hierarchy $hierarchy, StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, FormFactoryInterface $formFactory, Session $session, Request $request, Environment $twig, Key $key, string $nodeId, string $fieldId, RedirectHandler $redirectHandler): array|RedirectResponse
    {
        $node = $storageConnection->getQueryService()->findNode($key->getId(), $nodeId);
        $updateService = $storageConnection->getUpdateService();

        $editForm = $formFactory->create(
            EditNodeFieldType::class,
            [
                'fields' => $key->getNodeFieldValues($node),
            ],
            [
                'key' => $key,
                'nodeId' => $nodeId,
                'fieldId' => $fieldId,
                'hierarchySlug' => $hierarchy->getSlug(),
                'storageConnection' => $storageConnection,
                'action' => $urlGen->generate('update_node_field', [
                    'hierarchySlug' => $hierarchy->getSlug(),
                    'keyId' => $key->getId(),
                    'nodeId' => $nodeId,
                    'fieldId' => $fieldId,
                ]),
                'method' => 'POST',
            ]
        );

        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $storageConnection->getUpdateService()->updateNode($key->getId(), $nodeId, $editForm->getData()['fields']);

            $then = $request->request->get('_then', null);

            $session->getFlashBag()->add('success', 'Node Updated');
        } else {
            return [
                'hierarchy' => $hierarchy,
                'key' => $key,
                'node' => $node,
                'field' => $key->getField($fieldId),
                'parentNodes' => $storageConnection->getQueryService()->findParentNodes($key->getId(), $nodeId),
                'editForm' => $editForm->createView(),
            ];
        }

        return new RedirectResponse($urlGen->generate('edit_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'nodeId' => $nodeId]));
    }

    /**
     * @return array<string,mixed>
     */
    #[Route('/{hierarchySlug}/{keyId}', name: 'list_root_nodes', methods: 'GET')]
    #[Template('hierarchy/list_root_nodes.html.twig')]
    public function listRootNodes(Request $request, Hierarchy $hierarchy, StorageConnection $storageConnection, Key $key): array
    {
        $nodeCollection = $storageConnection->getQueryService()->findAllNodes($key->getId(), $request->query->has('deep'));

        return [
            'hierarchy' => $hierarchy,
            'key' => $key,
            'nodeCollection' => $nodeCollection,
            'parentNodes' => new MultiCollection(null, null, [], null, null),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    #[Route('/{hierarchySlug}/{keyId}/{nodeId}/{childKeyId}', name: 'list_child_nodes', methods: 'GET')]
    #[Template('hierarchy/list_child_nodes.html.twig')]
    public function listChildNodes(Hierarchy $hierarchy, StorageConnection $storageConnection, Key $key, string $nodeId, Key $childKey): array
    {
        return [
            'hierarchy' => $hierarchy,
            'key' => $key,
            'childKey' => $childKey,
            'node' => $storageConnection->getQueryService()->findNode($key->getId(), $nodeId),
            'parentNodes' => $storageConnection->getQueryService()->findParentNodes($key->getId(), $nodeId),
            'nodeCollection' => $storageConnection->getQueryService()->findNodeChildren($key->getId(), $nodeId, $childKey->getId()),
        ];
    }
}
