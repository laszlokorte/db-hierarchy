<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Doctrine\DBAL\Connection;

use App\Hierarchy\Schema\RecursiveLoader;
use App\Hierarchy\Schema\Hierarchy;
use App\Hierarchy\Schema\Key;
use App\Hierarchy\Schema\Field;
use App\Hierarchy\Storage\Relational\SchemaBuilder;
use App\Hierarchy\Storage\Relational\Exception\DeletionBlockedException;
use App\Hierarchy\Storage\Relational\Dialect\Sqlite;
use App\Hierarchy\Storage\Relational\StorageConnection;
use App\Hierarchy\Data\MultiCollection;

class HierarchyController {
	
	#[Route('/{hierarchySlug}', name: 'hierarchy_root', methods: 'GET', defaults: ['hierarchySlug' => 'system'])]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
	#[Template()]
    public function root(Hierarchy $hierarchy, StorageConnection $storageConnection)
    {
    	return [
            'hierarchy' => $hierarchy,
    		'rootNodes' => $storageConnection->getFetcher()->findAllRootNodes(),
    	];
    }

    #[Route('/{hierarchySlug}/_full-tree', name: 'hierarchy_tree', methods: 'GET')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
	#[Template()]
    public function tree(Hierarchy $hierarchy, StorageConnection $storageConnection)
    {       
    	return [
            'hierarchy' => $hierarchy,
    		'hierarchyNodes' => $storageConnection->getFetcher()->findAllHierarchyNodes(),
    	];
    }

    #[Route('/{hierarchySlug}/_setup', name: 'show_hierarchy_setup', methods: 'GET')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
	#[Template()]
    public function showSetup(Hierarchy $hierarchy, StorageConnection $storageConnection)
    {
    	return [
            'hierarchy' => $hierarchy,
    		'installer' => $storageConnection->getInstaller(),
    		'adapter' => new Sqlite(),
    	];
    }

    #[Route('/{hierarchySlug}/_setup', name: 'hierarchy_setup', methods: 'POST')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
    public function uninstall(Request $request, Session $session, UrlGeneratorInterface $urlGen, Hierarchy $hierarchy, StorageConnection $storageConnection)
    {
        $storageConnection->getInstaller()->createSchema(true, $request->request->get('only_views', false));

        $session->getFlashBag()->add('success', 'Schema has been updated.');

        return new RedirectResponse($urlGen->generate('show_hierarchy_setup', ['hierarchySlug' => $hierarchy->getSlug()]));
    }

    #[Route('/{hierarchySlug}/_uninstall', name: 'hierarchy_uninstall', methods: 'POST')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
    public function setup(Request $request, Session $session, UrlGeneratorInterface $urlGen, Hierarchy $hierarchy, StorageConnection $storageConnection)
    {
        $storageConnection->getInstaller()->dropSchema();

        $session->getFlashBag()->add('success', 'Schema has been removed.');

        return new RedirectResponse($urlGen->generate('show_hierarchy_setup', ['hierarchySlug' => $hierarchy->getSlug()]));
    }

    #[Route('/{hierarchySlug}/_diagnosis', name: 'show_diagnosis', methods: 'GET')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
	#[Template()]
    public function diagnosis(Hierarchy $hierarchy, StorageConnection $storageConnection)
    {
    	$diagnosis = $storageConnection->getFetcher()->findAllDefects();

    	return [
            'hierarchy' => $hierarchy,
    		'diagnosis' => $diagnosis,
    	];
    }

    #[Route('/{hierarchySlug}/_repair', name: 'repair', methods: 'POST')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
    public function repairDefects(UrlGeneratorInterface $urlGen, Hierarchy $hierarchy, StorageConnection $storageConnection)
    {
    	$storageConnection->getCommander()->repairAll();

    	return new RedirectResponse($urlGen->generate('show_diagnosis', ['hierarchySlug' => $hierarchy->getSlug()]));
    }

    #[Route('/{hierarchySlug}/_repair/{keyId}', name: 'repair_key', methods: 'POST')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
    #[ParamConverter('key')]
    public function repairKeyDefects(UrlGeneratorInterface $urlGen, Hierarchy $hierarchy, StorageConnection $storageConnection, Key $key)
    {
    	$storageConnection->getCommander()->repairKey($key->getId());

    	return new RedirectResponse($urlGen->generate('show_diagnosis', ['hierarchySlug' => $hierarchy->getSlug()]));
    }



    #[Route('/{hierarchySlug}_all/{keyId}.json', name: 'list_all_nodes', methods: 'GET')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
    #[ParamConverter('key')]
    #[Template()]
    public function listAllNodes(Hierarchy $hierarchy, StorageConnection $storageConnection, Key $key)
    {
        $all = $storageConnection->getFetcher()->findAllNodes($key->getId());
        return new JsonResponse([
            'keyId' => $key->getId(),
            'nodes' => array_map(fn($nodeId) => [
                'nodeId' => $nodeId,
                'label' => $key->summarize($all->getNode($nodeId), true),
            ], $all->getIds()),
        ]);
    }

    #[Route('/{hierarchySlug}/{keyId}({field})/{nodeId}', name: 'show_node_field', methods: 'GET')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
    #[ParamConverter('key')]
    #[ParamConverter('field')]
	#[Template()]
    public function showNodeField(Hierarchy $hierarchy, StorageConnection $storageConnection, Key $key, $nodeId, Field $field)
    {
    	return new JsonResponse((object)[
            'keyId' => $key->getId(),
            'nodeId' => $nodeId,
            'field' => $field->getId(),
            'value' => $field->readObjectOf(
                $storageConnection->getFetcher()->findNodeField($key->getId(), $nodeId, $field->getId())
            ),
        ]);
    }

    #[Route('/{hierarchySlug}/{keyId}/+', name: 'new_root_node', methods: 'GET')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
    #[ParamConverter('key')]
	#[Template()]
    public function newRootNode(Request $request, UrlGeneratorInterface $urlGen, Hierarchy $hierarchy, StorageConnection $storageConnection, Key $key)
    {
        if($key->isSingleton()) {
            if(!$storageConnection->getFetcher()->findRootNodes($key->getId())->isEmpty()) {
                return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId()]));
            }
        }

    	return [
            'hierarchy' => $hierarchy,
    		'key' => $key,
            'parentNodes' => new MultiCollection(null, null, [], null, null),
    	];
    }

    #[Route('/{hierarchySlug}/{keyId}/{nodeId}/{childKeyId}/+', name: 'new_child_node', methods: 'GET')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
    #[ParamConverter('key')]
    #[ParamConverter('childKey')]
	#[Template()]
    public function newChildNode(UrlGeneratorInterface $urlGen, Hierarchy $hierarchy, StorageConnection $storageConnection, Key $key, $nodeId, Key $childKey)
    {

        if($childKey->isSingleton()) {
            if(!$storageConnection->getFetcher()->findNodeChildren($key->getId(), $nodeId, $childKey->getId())->isEmpty()) {
                return new RedirectResponse($urlGen->generate('list_child_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'nodeId' => $nodeId, 'childKeyId' => $childKey->getId()]));
            }
        }

    	return [
            'hierarchy' => $hierarchy,
    		'key' => $key,
    		'childKey' => $childKey,
            'node' => $storageConnection->getFetcher()->findNode($key->getId(), $nodeId),
            'parentNodes' => $storageConnection->getFetcher()->findParentNodes($key->getId(), $nodeId),
    	];
    }

    #[Route('/{hierarchySlug}/{keyId}/{nodeId}', name: 'show_node', methods: 'GET')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
    #[ParamConverter('key')]
	#[Template()]
    public function showNode(Hierarchy $hierarchy, StorageConnection $storageConnection, Key $key, $nodeId)
    {
    	return [
            'hierarchy' => $hierarchy,
    		'key' => $key,
            'node' => $storageConnection->getFetcher()->findNode($key->getId(), $nodeId),
            'parentNodes' => $storageConnection->getFetcher()->findParentNodes($key->getId(), $nodeId),
            'childNodes' => $storageConnection->getFetcher()->findNodeAllChildren($key->getId(), $nodeId),
    	];
    }

    #[Route('/{hierarchySlug}/{keyId}/{nodeId}/_edit', name: 'edit_node', methods: 'GET')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
    #[ParamConverter('key')]
	#[Template()]
    public function editNode(Hierarchy $hierarchy, StorageConnection $storageConnection, Key $key, $nodeId)
    {
    	return [
            'hierarchy' => $hierarchy,
    		'key' => $key,
    		'node' => $storageConnection->getFetcher()->findNode($key->getId(), $nodeId),
            'parentNodes' => $storageConnection->getFetcher()->findParentNodes($key->getId(), $nodeId),
    	];
    }

    #[Route('/{hierarchySlug}/{keyId}/{nodeId}/-', name: 'delete_node', methods: 'POST')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
    #[ParamConverter('key')]
    public function deleteNode(Hierarchy $hierarchy, StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, Session $session, Request $request, Key $key, $nodeId)
    {
    	$lastParent = $storageConnection->getFetcher()->findNodeDirectParent($key->getId(), $nodeId);

        try {
            $storageConnection->getCommander()->deleteNode($key->getId(), $nodeId);
        } catch(DeletionBlockedException $e) {
            return new RedirectResponse($urlGen->generate('ask_delete_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'nodeId' => $nodeId]));
        }

		$then = $request->request->get('_then', null);

		$session->getFlashBag()->add('success', 'Node Deleted');


        if($then === 'list') {
            if($lastParent) {
                $args = array_merge($lastParent->pathArgs(), ['hierarchySlug' => $hierarchy->getSlug(), 'childKeyId' => $key->getId()]);
                return new RedirectResponse($urlGen->generate('list_child_nodes', $args));
            } else {
                return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId()]));
            }
        } elseif($then === 'root_list') {
            return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId()]));
        } elseif($then === 'parent') {
            if($lastParent) {
                return new RedirectResponse($urlGen->generate('show_node', array_merge($lastParent->pathArgs(), ['hierarchySlug' => $hierarchy->getSlug()])));
            } else {
                return new RedirectResponse($urlGen->generate('hierarchy_root', ['hierarchySlug' => $hierarchy->getSlug()]));
            }
    	} else {
            return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId()]));
        } 
    }

    #[Route('/{hierarchySlug}/{keyId}/{nodeId}/-', name: 'ask_delete_node', methods: 'GET')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
    #[ParamConverter('key')]
	#[Template()]
    public function askDeleteNode(Hierarchy $hierarchy, StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, Key $key, $nodeId)
    {
        $deletionPlan = $storageConnection->getCommander()->getDeletionPlan($key->getId(), $nodeId);

		return [
            'hierarchy' => $hierarchy,
    		'key' => $key,
    		'node' => $storageConnection->getFetcher()->findNode($key->getId(), $nodeId),
            'parentNodes' => $storageConnection->getFetcher()->findParentNodes($key->getId(), $nodeId),
            'deletionPlan' => $deletionPlan,
    	];
    }

    #[Route('/{hierarchySlug}/{keyId}', name: 'create_node', methods: 'POST')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
    #[ParamConverter('key')]
    public function createNode(Hierarchy $hierarchy, StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, Session $session, Request $request, Key $key)
    {
    	$scope = $request->request->get('scope', NULL);
    	$parent = $request->request->get('parent', NULL);
    	$newId = $storageConnection->getCommander()->createNode($key->getId(), $request->request->get('field', []), $scope, $parent);

		$then = $request->request->get('_then', null);
		
		$session->getFlashBag()->add('success', 'Node Created');

		if($then === 'form') {
            if($parent) {
                return new RedirectResponse($urlGen->generate('new_child_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'childKeyId' => $key->getId(), 'nodeId' => $parent]));
            } elseif ($scope) {
                $parentKey = $key->getScopeKey()->getId();
                return new RedirectResponse($urlGen->generate('new_child_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $parentKey, 'childKeyId' => $key->getId(), 'nodeId' => $scope]));
            } else {
                return new RedirectResponse($urlGen->generate('new_root_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId()]));
            }
        } elseif($then === 'root_form') {
            return new RedirectResponse($urlGen->generate('new_root_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId()]));
        } elseif($then === 'new') {
            return new RedirectResponse($urlGen->generate('show_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'nodeId' => $newId]));
        } elseif($then === 'list') {
            if($parent) {
                return new RedirectResponse($urlGen->generate('list_child_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'childKeyId' => $key->getId(), 'nodeId' => $parent]));
            } elseif ($scope) {
                $parentKey = $key->getScopeKey()->getId();
                return new RedirectResponse($urlGen->generate('list_child_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $parentKey, 'childKeyId' => $key->getId(), 'nodeId' => $scope]));
            } else {
                return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId()]));
            }
        } elseif($then === 'root_list') {
            return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId()]));
        } else {
            if($parent) {
                return new RedirectResponse($urlGen->generate('show_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'nodeId' => $parent]));
            } elseif ($scope) {
                $parentKey = $key->getScopeKey()->getId();
                return new RedirectResponse($urlGen->generate('show_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $parentKey, 'nodeId' => $scope]));
            } else {
                return new RedirectResponse($urlGen->generate('hierarchy_root', ['hierarchySlug' => $hierarchy->getSlug(), 'hierarchySlug' => $hierarchy->getSlug()]));
            }
        }
    }

    #[Route('/{hierarchySlug}/{keyId}/{nodeId}/_move', name: 'ask_move_node', methods: 'GET')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
    #[ParamConverter('key')]
    #[Template()]
    public function askMoveNode(Hierarchy $hierarchy, StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, Session $session, Request $request, Key $key, $nodeId)
    {
        if(!$key->isNested()) {
            throw new NotFoundHttpException(sprintf('%s are not nested', $key->getLabel()->getPlural()));
        }

        return [
            'hierarchy' => $hierarchy,
            'key' => $key,
            'moveTargets' => $storageConnection->getFetcher()->findNodeMoveTargets($key->getId(), $nodeId),
            'node' => $storageConnection->getFetcher()->findNode($key->getId(), $nodeId),
            'parentNodes' => $storageConnection->getFetcher()->findParentNodes($key->getId(), $nodeId),
        ];
    }

    #[Route('/{hierarchySlug}/{keyId}/{nodeId}/_move', name: 'move_node', methods: 'POST')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
    #[ParamConverter('key')]
    public function moveNode(Hierarchy $hierarchy, StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, Session $session, Request $request, Key $key, $nodeId)
    {
        if(!$key->isNested()) {
            throw new NotFoundHttpException(sprintf('%s are not nested', $key->getLabel()->getPlural()));
        }

        list($scope, $parent) = explode('/', $request->request->get('target_scope-parent','/'), 2);
        
        $storageConnection->getCommander()->moveNode($key->getId(), $nodeId, $scope?:null, $parent?:null);

        $session->getFlashBag()->add('success', 'Node Moved');


        $then = $request->request->get('_then', null);

        if($then === 'tree') {
            return new RedirectResponse($urlGen->generate('hierarchy_tree', ['hierarchySlug' => $hierarchy->getSlug()]));
        } else {
            return new RedirectResponse($urlGen->generate('ask_move_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'nodeId' => $nodeId]));
        }
    }

    #[Route('/{hierarchySlug}/{keyId}/{nodeId}/_order', name: 'ask_order_node', methods: 'GET')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
    #[ParamConverter('key')]
    #[Template()]
    public function askOrderNode(Hierarchy $hierarchy, StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, Session $session, Request $request, Key $key, $nodeId)
    {
        if(!$k->isOrdered()) {
            throw new NotFoundHttpException(sprintf('%s are not ordered', $key->getLabel()->getPlural()));
        }

        return [
            'hierarchy' => $hierarchy,
            'key' => $key,
            'orderTargets' => $storageConnection->getFetcher()->findNodeSiblings($key->getId(), $nodeId),
            'node' => $storageConnection->getFetcher()->findNode($key->getId(), $nodeId),
            'parentNodes' => $storageConnection->getFetcher()->findParentNodes($key->getId(), $nodeId),
        ];
    }

    #[Route('/{hierarchySlug}/{keyId}/{nodeId}/_order', name: 'order_node', methods: 'POST')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
    #[ParamConverter('key')]
    public function orderNode(Hierarchy $hierarchy, StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, Session $session, Request $request, Key $key, $nodeId)
    {

        if(!$key->isOrdered()) {
            throw new NotFoundHttpException(sprintf('%s are not ordered', $key->getLabel()->getPlural()));
        }
        
        $target = $request->request->get('target_order');

        if(!empty($target)) {
            $storageConnection->getCommander()->orderNode($key->getId(), $nodeId, $target);

            $session->getFlashBag()->add('success', 'Node Reordered');
        }

        $then = $request->request->get('_then', null);

        if($then === 'tree') {
            return new RedirectResponse($urlGen->generate('hierarchy_tree', ['hierarchySlug' => $hierarchy->getSlug()]));
        } elseif($then === 'list') {
            $directParent = $storageConnection->getFetcher()->findNodeDirectParent($key->getId(), $nodeId);

            if($directParent) {
                return new RedirectResponse($urlGen->generate('list_child_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $directParent->getKey(), 'nodeId' => $directParent->getId(), 'childKeyId' => $key->getId()]));

            } else {
                return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId()]));
            }
        } elseif($then === 'root_list') {
            return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId()]));
        } elseif($then === 'parent') {
            $directParent = $storageConnection->getFetcher()->findNodeDirectParent($key->getId(), $nodeId);

            if($directParent) {
                return new RedirectResponse($urlGen->generate('show_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $directParent->getKey(), 'nodeId' => $directParent->getId()]));

            } else {
                return new RedirectResponse($urlGen->generate('hierarchy_root', ['hierarchySlug' => $hierarchy->getSlug()]));
            }
        } else {
            return new RedirectResponse($urlGen->generate('ask_order_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'nodeId' => $nodeId]));
        }
    }

    #[Route('/{hierarchySlug}/{keyId}/{nodeId}', name: 'update_node', methods: 'POST')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
    #[ParamConverter('key')]
    public function updateNode(Hierarchy $hierarchy, StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, Session $session, Request $request, Key $key, $nodeId)
    {
		$storageConnection->getCommander()->updateNode($key, $nodeId, $request->request->get('field', []));

		$then = $request->request->get('_then', null);

		$session->getFlashBag()->add('success', 'Node Updated');

        if($then === 'root') {
            return new RedirectResponse($urlGen->generate('hierarchy_root', ['hierarchySlug' => $hierarchy->getSlug()]));
        } elseif($then === 'list') {
            $lastParent = $storageConnection->getFetcher()->findNodeDirectParent($key->getId(), $nodeId);
            if($lastParent) {
                $args = array_merge($lastParent->pathArgs(), ['hierarchySlug' => $hierarchy->getSlug(), 'childKeyId' => $key->getId()]);
                return new RedirectResponse($urlGen->generate('list_child_nodes', $args));
            } else {
                return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId()]));
            }
        } elseif($then === 'edit') {
    		return new RedirectResponse($urlGen->generate('edit_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'nodeId' => $nodeId]));
		} else {
    		return new RedirectResponse($urlGen->generate('show_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'nodeId' => $nodeId]));
		}
    }

    #[Route('/{hierarchySlug}/{keyId}', name: 'list_root_nodes', methods: 'GET')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
    #[ParamConverter('key')]
	#[Template()]
    public function listRootNodes(Hierarchy $hierarchy, StorageConnection $storageConnection, Key $key)
    {
    	return [
            'hierarchy' => $hierarchy,
    		'key' => $key,
    		'nodeCollection' => $storageConnection->getFetcher()->findRootNodes($key->getId()),
            'parentNodes' => new MultiCollection(null, null, [], null, null),
    	];
    }

    #[Route('/{hierarchySlug}/{keyId}/{nodeId}/{childKeyId}', name: 'list_child_nodes', methods: 'GET')]
    #[ParamConverter('key')]
    #[ParamConverter('childKey')]
	#[Template()]
    public function listChildNodes(Hierarchy $hierarchy, StorageConnection $storageConnection, Key $key, $nodeId, Key $childKey)
    {
    	return [
            'hierarchy' => $hierarchy,
    		'key' => $key,
    		'childKey' => $childKey,
            'node' => $storageConnection->getFetcher()->findNode($key->getId(), $nodeId),
            'parentNodes' => $storageConnection->getFetcher()->findParentNodes($key->getId(), $nodeId),
            'nodeCollection' => $storageConnection->getFetcher()->findNodeChildren($key->getId(), $nodeId, $childKey->getId()),
    	];
    }

}