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
        try {
            $rootKeys = $hierarchy->getRootKeys();
        } catch(\Exception $e) {
            $rootKeys = [];
        }

    	return [
            'hierarchy' => $hierarchy,
    		'installer' => $storageConnection->getInstaller(),
    		'adapter' => new Sqlite(),
    	];
    }

    #[Route('/{hierarchySlug}/_setup', name: 'hierarchy_setup', methods: 'POST')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
    public function uninstall(Request $request, Session $session, UrlGeneratorInterface $urlGen, Hierarchy $hierarchy, StorageConnection $storageConnection, Connection $db)
    {
        $storageConnection->getInstaller()->createSchema(true, $request->request->get('only_views', false));

        $session->getFlashBag()->add('success', 'Schema has been updated.');

        return new RedirectResponse($urlGen->generate('show_hierarchy_setup', ['hierarchySlug' => $hierarchy->getSlug()]));
    }

    #[Route('/{hierarchySlug}/_uninstall', name: 'hierarchy_uninstall', methods: 'POST')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
    public function setup(Request $request, Session $session, UrlGeneratorInterface $urlGen, Hierarchy $hierarchy, StorageConnection $storageConnection, Connection $db)
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

    #[Route('/{hierarchySlug}/_repair/{key}', name: 'repair_key', methods: 'POST')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
    public function repairKeyDefects(UrlGeneratorInterface $urlGen, Hierarchy $hierarchy, StorageConnection $storageConnection, $key)
    {
    	$storageConnection->getCommander()->repairKey($key);

    	return new RedirectResponse($urlGen->generate('show_diagnosis', ['hierarchySlug' => $hierarchy->getSlug()]));
    }



    #[Route('/{hierarchySlug}_all/{key}.json', name: 'list_all_nodes', methods: 'GET')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
    #[Template()]
    public function listAllNodes(Hierarchy $hierarchy, StorageConnection $storageConnection, $key)
    {
        $all = $storageConnection->getFetcher()->findAllNodes($key);
        $key = $hierarchy->getKey($key);
        return new JsonResponse([
            'key' => $key->getId(),
            'nodes' => array_map(fn($id) => [
                'id' => $id,
                'label' => $key->summarize($all->getNode($id), true),
            ], $all->getIds()),
        ]);
    }

    #[Route('/{hierarchySlug}/{key}({field})/{id}', name: 'show_node_field', methods: 'GET')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
	#[Template()]
    public function showNodeField(Hierarchy $hierarchy, StorageConnection $storageConnection, $key, $id, $field)
    {
    	return new JsonResponse((object)[
            'key' => $key,
            'id' => $id,
            'field' => $field,
            'value' => $hierarchy->getKey($key)->getField($field)->readObjectOf(
                $storageConnection->getFetcher()->findNodeField($key, $id, $field)
            ),
        ]);
    }

    #[Route('/{hierarchySlug}/{key}/+', name: 'new_root_node', methods: 'GET')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
	#[Template()]
    public function newRootNode(Request $request, UrlGeneratorInterface $urlGen, Hierarchy $hierarchy, StorageConnection $storageConnection, $key)
    {
        $k = $hierarchy->getKey($key);

        if($k->isSingleton()) {
            if(!$storageConnection->getFetcher()->findRootNodes($key)->isEmpty()) {
                return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'key' => $key]));
            }
        }

    	return [
            'hierarchy' => $hierarchy,
    		'key' => $k,
            'parentNodes' => new MultiCollection(null, null, [], null, null),
    	];
    }

    #[Route('/{hierarchySlug}/{key}/{id}/{childKey}/+', name: 'new_child_node', methods: 'GET')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
	#[Template()]
    public function newChildNode(UrlGeneratorInterface $urlGen, Hierarchy $hierarchy, StorageConnection $storageConnection, $key, $id, $childKey)
    {
        $k = $hierarchy->getKey($key);
        $ck = $hierarchy->getKey($childKey);

        if($ck->isSingleton()) {
            if(!$storageConnection->getFetcher()->findNodeChildren($key, $id, $childKey)->isEmpty()) {
                return new RedirectResponse($urlGen->generate('list_child_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'key' => $key, 'id' => $id, 'childKey' => $childKey]));
            }
        }

    	return [
            'hierarchy' => $hierarchy,
    		'key' => $k,
    		'childKey' => $ck,
            'node' => $storageConnection->getFetcher()->findNode($key, $id),
            'parentNodes' => $storageConnection->getFetcher()->findParentNodes($key, $id),
    	];
    }

    #[Route('/{hierarchySlug}/{key}/{id}', name: 'show_node', methods: 'GET')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
	#[Template()]
    public function showNode(Hierarchy $hierarchy, StorageConnection $storageConnection, $key, $id)
    {
    	return [
            'hierarchy' => $hierarchy,
    		'key' => $hierarchy->getKey($key),
            'node' => $storageConnection->getFetcher()->findNode($key, $id),
            'parentNodes' => $storageConnection->getFetcher()->findParentNodes($key, $id),
            'childNodes' => $storageConnection->getFetcher()->findNodeAllChildren($key, $id),
    	];
    }

    #[Route('/{hierarchySlug}/{key}/{id}/_edit', name: 'edit_node', methods: 'GET')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
	#[Template()]
    public function editNode(Hierarchy $hierarchy, StorageConnection $storageConnection, $key, $id)
    {
    	return [
            'hierarchy' => $hierarchy,
    		'key' => $hierarchy->getKey($key),
    		'node' => $storageConnection->getFetcher()->findNode($key, $id),
            'parentNodes' => $storageConnection->getFetcher()->findParentNodes($key, $id),
    	];
    }

    #[Route('/{hierarchySlug}/{key}/{id}/-', name: 'delete_node', methods: 'POST')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
    public function deleteNode(Hierarchy $hierarchy, StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, Session $session, Request $request, $key, $id)
    {
    	$lastParent = $storageConnection->getFetcher()->findNodeDirectParent($key, $id);

        try {
            $storageConnection->getCommander()->deleteNode($key, $id);
        } catch(DeletionBlockedException $e) {
            return new RedirectResponse($urlGen->generate('ask_delete_node', ['hierarchySlug' => $hierarchy->getSlug(), 'key' => $key, 'id' => $id]));
        }

		$then = $request->request->get('_then', null);

		$session->getFlashBag()->add('success', 'Node Deleted');


        if($then === 'list') {
            if($lastParent) {
                $args = array_merge($lastParent->pathArgs(), ['hierarchySlug' => $hierarchy->getSlug(), 'childKey' => $key]);
                return new RedirectResponse($urlGen->generate('list_child_nodes', $args));
            } else {
                return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'key' => $key]));
            }
        } elseif($then === 'root_list') {
            return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'key' => $key]));
        } elseif($then === 'parent') {
            if($lastParent) {
                return new RedirectResponse($urlGen->generate('show_node', array_merge($lastParent->pathArgs(), ['hierarchySlug' => $hierarchy->getSlug()])));
            } else {
                return new RedirectResponse($urlGen->generate('hierarchy_root', ['hierarchySlug' => $hierarchy->getSlug()]));
            }
    	} else {
            return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'key' => $key]));
        } 
    }

    #[Route('/{hierarchySlug}/{key}/{id}/-', name: 'ask_delete_node', methods: 'GET')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
	#[Template()]
    public function askDeleteNode(Hierarchy $hierarchy, StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, $key, $id)
    {
        $deletionPlan = $storageConnection->getCommander()->getDeletionPlan($key, $id);

		return [
            'hierarchy' => $hierarchy,
    		'key' => $hierarchy->getKey($key),
    		'node' => $storageConnection->getFetcher()->findNode($key, $id),
            'parentNodes' => $storageConnection->getFetcher()->findParentNodes($key, $id),
            'deletionPlan' => $deletionPlan,
    	];
    }

    #[Route('/{hierarchySlug}/{key}', name: 'create_node', methods: 'POST')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
    public function createNode(Hierarchy $hierarchy, StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, Session $session, Request $request, $key)
    {
    	$key = $hierarchy->getKey($key);
    	$scope = $request->request->get('scope', NULL);
    	$parent = $request->request->get('parent', NULL);
    	$newId = $storageConnection->getCommander()->createNode($key->getId(), $request->request->get('field', []), $scope, $parent);

		$then = $request->request->get('_then', null);
		
		$session->getFlashBag()->add('success', 'Node Created');

		if($then === 'form') {
            if($parent) {
                return new RedirectResponse($urlGen->generate('new_child_node', ['hierarchySlug' => $hierarchy->getSlug(), 'key' => $key->getId(), 'childKey' => $key->getId(), 'id' => $parent]));
            } elseif ($scope) {
                $parentKey = $key->getScopeKey()->getId();
                return new RedirectResponse($urlGen->generate('new_child_node', ['hierarchySlug' => $hierarchy->getSlug(), 'key' => $parentKey, 'childKey' => $key->getId(), 'id' => $scope]));
            } else {
                return new RedirectResponse($urlGen->generate('new_root_node', ['hierarchySlug' => $hierarchy->getSlug(), 'key' => $key->getId()]));
            }
        } elseif($then === 'root_form') {
            return new RedirectResponse($urlGen->generate('new_root_node', ['hierarchySlug' => $hierarchy->getSlug(), 'key' => $key->getId()]));
        } elseif($then === 'new') {
            return new RedirectResponse($urlGen->generate('show_node', ['hierarchySlug' => $hierarchy->getSlug(), 'key' => $key->getId(), 'id' => $newId]));
        } elseif($then === 'list') {
            if($parent) {
                return new RedirectResponse($urlGen->generate('list_child_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'key' => $key->getId(), 'childKey' => $key->getId(), 'id' => $parent]));
            } elseif ($scope) {
                $parentKey = $key->getScopeKey()->getId();
                return new RedirectResponse($urlGen->generate('list_child_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'key' => $parentKey, 'childKey' => $key->getId(), 'id' => $scope]));
            } else {
                return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'key' => $key->getId()]));
            }
        } elseif($then === 'root_list') {
            return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'key' => $key->getId()]));
        } else {
            if($parent) {
                return new RedirectResponse($urlGen->generate('show_node', ['hierarchySlug' => $hierarchy->getSlug(), 'key' => $key->getId(), 'id' => $parent]));
            } elseif ($scope) {
                $parentKey = $key->getScopeKey()->getId();
                return new RedirectResponse($urlGen->generate('show_node', ['hierarchySlug' => $hierarchy->getSlug(), 'key' => $parentKey, 'id' => $scope]));
            } else {
                return new RedirectResponse($urlGen->generate('hierarchy_root', ['hierarchySlug' => $hierarchy->getSlug(), 'hierarchySlug' => $hierarchy->getSlug()]));
            }
        }
    }

    #[Route('/{hierarchySlug}/{key}/{id}/_move', name: 'ask_move_node', methods: 'GET')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
    #[Template()]
    public function askMoveNode(Hierarchy $hierarchy, StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, Session $session, Request $request, $key, $id)
    {
        $k = $hierarchy->getKey($key);
        if(!$k->isNested()) {
            throw new NotFoundHttpException(sprintf('%s are not nested', $k->getLabel()->getPlural()));
        }

        return [
            'hierarchy' => $hierarchy,
            'key' => $k,
            'moveTargets' => $storageConnection->getFetcher()->findNodeMoveTargets($key, $id),
            'node' => $storageConnection->getFetcher()->findNode($key, $id),
            'parentNodes' => $storageConnection->getFetcher()->findParentNodes($key, $id),
        ];
    }

    #[Route('/{hierarchySlug}/{key}/{id}/_move', name: 'move_node', methods: 'POST')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
    public function moveNode(Hierarchy $hierarchy, StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, Session $session, Request $request, $key, $id)
    {
        $k = $hierarchy->getKey($key);
        if(!$k->isNested()) {
            throw new NotFoundHttpException(sprintf('%s are not nested', $k->getLabel()->getPlural()));
        }

        list($scope, $parent) = explode('/', $request->request->get('target_scope-parent','/'), 2);
        
        $storageConnection->getCommander()->moveNode($key, $id, $scope?:null, $parent?:null);

        $session->getFlashBag()->add('success', 'Node Moved');


        $then = $request->request->get('_then', null);

        if($then === 'tree') {
            return new RedirectResponse($urlGen->generate('hierarchy_tree', ['hierarchySlug' => $hierarchy->getSlug()]));
        } else {
            return new RedirectResponse($urlGen->generate('ask_move_node', ['hierarchySlug' => $hierarchy->getSlug(), 'key' => $key, 'id' => $id]));
        }
    }

    #[Route('/{hierarchySlug}/{key}/{id}/_order', name: 'ask_order_node', methods: 'GET')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
    #[Template()]
    public function askOrderNode(Hierarchy $hierarchy, StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, Session $session, Request $request, $key, $id)
    {
        $k = $hierarchy->getKey($key);
        if(!$k->isOrdered()) {
            throw new NotFoundHttpException(sprintf('%s are not ordered', $k->getLabel()->getPlural()));
        }

        return [
            'hierarchy' => $hierarchy,
            'key' => $hierarchy->getKey($key),
            'orderTargets' => $storageConnection->getFetcher()->findNodeSiblings($key, $id),
            'node' => $storageConnection->getFetcher()->findNode($key, $id),
            'parentNodes' => $storageConnection->getFetcher()->findParentNodes($key, $id),
        ];
    }

    #[Route('/{hierarchySlug}/{key}/{id}/_order', name: 'order_node', methods: 'POST')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
    public function orderNode(Hierarchy $hierarchy, StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, Session $session, Request $request, $key, $id)
    {

        $k = $hierarchy->getKey($key);
        if(!$k->isOrdered()) {
            throw new NotFoundHttpException(sprintf('%s are not ordered', $k->getLabel()->getPlural()));
        }
        
        $target = $request->request->get('target_order');

        if(!empty($target)) {
            $storageConnection->getCommander()->orderNode($key, $id, $target);

            $session->getFlashBag()->add('success', 'Node Reordered');
        }

        $then = $request->request->get('_then', null);

        if($then === 'tree') {
            return new RedirectResponse($urlGen->generate('hierarchy_tree', ['hierarchySlug' => $hierarchy->getSlug()]));
        } elseif($then === 'list') {
            $directParent = $storageConnection->getFetcher()->findNodeDirectParent($key, $id);

            if($directParent) {
                return new RedirectResponse($urlGen->generate('list_child_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'key' => $directParent->getKey(), 'id' => $directParent->getId(), 'childKey' => $key]));

            } else {
                return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'key' => $key]));
            }
        } elseif($then === 'root_list') {
            return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'key' => $key]));
        } elseif($then === 'parent') {
            $directParent = $storageConnection->getFetcher()->findNodeDirectParent($key, $id);

            if($directParent) {
                return new RedirectResponse($urlGen->generate('show_node', ['hierarchySlug' => $hierarchy->getSlug(), 'key' => $directParent->getKey(), 'id' => $directParent->getId()]));

            } else {
                return new RedirectResponse($urlGen->generate('hierarchy_root', ['hierarchySlug' => $hierarchy->getSlug()]));
            }
        } else {
            return new RedirectResponse($urlGen->generate('ask_order_node', ['hierarchySlug' => $hierarchy->getSlug(), 'key' => $key, 'id' => $id]));
        }
    }

    #[Route('/{hierarchySlug}/{key}/{id}', name: 'update_node', methods: 'POST')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
    public function updateNode(Hierarchy $hierarchy, StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, Session $session, Request $request, $key, $id)
    {
		$storageConnection->getCommander()->updateNode($key, $id, $request->request->get('field', []));

		$then = $request->request->get('_then', null);

		$session->getFlashBag()->add('success', 'Node Updated');

        if($then === 'root') {
            return new RedirectResponse($urlGen->generate('hierarchy_root', ['hierarchySlug' => $hierarchy->getSlug()]));
        } elseif($then === 'list') {
            $lastParent = $storageConnection->getFetcher()->findNodeDirectParent($key, $id);
            if($lastParent) {
                $args = array_merge($lastParent->pathArgs(), ['hierarchySlug' => $hierarchy->getSlug(), 'childKey' => $key]);
                return new RedirectResponse($urlGen->generate('list_child_nodes', $args));
            } else {
                return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'key' => $key]));
            }
        } elseif($then === 'edit') {
    		return new RedirectResponse($urlGen->generate('edit_node', ['hierarchySlug' => $hierarchy->getSlug(), 'key' => $key, 'id' => $id]));
		} else {
    		return new RedirectResponse($urlGen->generate('show_node', ['hierarchySlug' => $hierarchy->getSlug(), 'key' => $key, 'id' => $id]));
		}
    }

    #[Route('/{hierarchySlug}/{key}', name: 'list_root_nodes', methods: 'GET')]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
	#[Template()]
    public function listRootNodes(Hierarchy $hierarchy, StorageConnection $storageConnection, $key)
    {
    	return [
            'hierarchy' => $hierarchy,
    		'key' => $hierarchy->getKey($key),
    		'nodeCollection' => $storageConnection->getFetcher()->findRootNodes($key),
            'parentNodes' => new MultiCollection(null, null, [], null, null),
    	];
    }

    #[Route('/{hierarchySlug}/{key}/{id}/{childKey}', name: 'list_child_nodes', methods: 'GET')]
	#[Template()]
    public function listChildNodes(Hierarchy $hierarchy, StorageConnection $storageConnection, $key, $id, $childKey)
    {
    	return [
            'hierarchy' => $hierarchy,
    		'key' => $hierarchy->getKey($key),
    		'childKey' => $hierarchy->getKey($childKey),
            'node' => $storageConnection->getFetcher()->findNode($key, $id),
            'parentNodes' => $storageConnection->getFetcher()->findParentNodes($key, $id),
            'nodeCollection' => $storageConnection->getFetcher()->findNodeChildren($key, $id, $childKey),
    	];
    }

}