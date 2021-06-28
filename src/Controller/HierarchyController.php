<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Doctrine\DBAL\Connection;

use App\Hierarchy\Schema\SchemaRoot;
use App\Hierarchy\Storage\Relational\SchemaBuilder;
use App\Hierarchy\Storage\Relational\Exception\DeletionBlockedException;
use App\Hierarchy\Storage\Relational\Dialect\Sqlite;
use App\Hierarchy\Storage\Relational\StorageConnection;
use App\Hierarchy\Data\MultiCollection;

class HierarchyController {

	public function __construct(SchemaRoot $schema) {
		$this->schema = $schema;
	}
	
	#[Route('/', name: 'hierarchy_root', methods: 'GET')]
	#[Template()]
    public function root(StorageConnection $storageConnection)
    {
    	return [
    		'rootKeys' => $this->schema->getRootKeys(),
    		'rootNodes' => $storageConnection->getFetcher()->findAllRootNodes(),
    	];
    }

    #[Route('/_full-tree', name: 'hierarchy_tree', methods: 'GET')]
	#[Template()]
    public function tree(StorageConnection $storageConnection)
    {       
    	return [
    		'rootKeys' => $this->schema->getRootKeys(),
    		'hierarchy' => $storageConnection->getFetcher()->findAllHierarchyNodes(),
    	];
    }

    #[Route('/_setup', name: 'show_hierarchy_setup', methods: 'GET')]
	#[Template()]
    public function showSetup(StorageConnection $storageConnection)
    {
        try {
            $rootKeys = $this->schema->getRootKeys();
        } catch(\Exception $e) {
            $rootKeys = [];
        }

    	return [
    		'installer' => $storageConnection->getInstaller(),
    		'adapter' => new Sqlite(),
    		'rootKeys' => $rootKeys,
    	];
    }

    #[Route('/_setup', name: 'hierarchy_setup', methods: 'POST')]
    public function uninstall(Request $request, Session $session, UrlGeneratorInterface $urlGen, StorageConnection $storageConnection, Connection $db)
    {
        $storageConnection->getInstaller()->createSchema(true, $request->request->get('only_views', false));

        $session->getFlashBag()->add('success', 'Schema has been updated.');

        return new RedirectResponse($urlGen->generate('show_hierarchy_setup'));
    }

    #[Route('/_uninstall', name: 'hierarchy_uninstall', methods: 'POST')]
    public function setup(Request $request, Session $session, UrlGeneratorInterface $urlGen, StorageConnection $storageConnection, Connection $db)
    {
        $storageConnection->getInstaller()->dropSchema();

        $session->getFlashBag()->add('success', 'Schema has been removed.');

        return new RedirectResponse($urlGen->generate('show_hierarchy_setup'));
    }

    #[Route('/_diagnosis', name: 'show_diagnosis', methods: 'GET')]
	#[Template()]
    public function diagnosis(StorageConnection $storageConnection)
    {
    	$diagnosis = $storageConnection->getFetcher()->findAllDefects();

    	return [
    		'rootKeys' => $this->schema->getRootKeys(),
    		'diagnosis' => $diagnosis,
    	];
    }

    #[Route('/_repair', name: 'repair', methods: 'POST')]
    public function repairDefects(UrlGeneratorInterface $urlGen, StorageConnection $storageConnection)
    {
    	$storageConnection->getCommander()->repairAll();

    	return new RedirectResponse($urlGen->generate('show_diagnosis'));
    }

    #[Route('/_repair/{key}', name: 'repair_key', methods: 'POST')]
    public function repairKeyDefects(UrlGeneratorInterface $urlGen, StorageConnection $storageConnection, $key)
    {
    	$storageConnection->getCommander()->repairKey($key);

    	return new RedirectResponse($urlGen->generate('show_diagnosis'));
    }



    #[Route('_all/{key}.json', name: 'list_all_nodes', methods: 'GET')]
    #[Template()]
    public function listAllNodes(StorageConnection $storageConnection, $key)
    {
        $all = $storageConnection->getFetcher()->findAllNodes($key);
        $key = $this->schema->getKey($key);
        return new JsonResponse([
            'key' => $key->getId(),
            'nodes' => array_map(fn($id) => [
                'id' => $id,
                'label' => $key->summarize($all->getNode($id), true),
            ], $all->getIds()),
        ]);
    }

    #[Route('/{key}({field})/{id}', name: 'show_node_field', methods: 'GET')]
	#[Template()]
    public function showNodeField(StorageConnection $storageConnection, $key, $id, $field)
    {
    	return new JsonResponse((object)[
            'key' => $key,
            'id' => $id,
            'field' => $field,
            'value' => $this->schema->getKey($key)->getField($field)->readObjectOf(
                $storageConnection->getFetcher()->findNodeField($key, $id, $field)
            ),
        ]);
    }

    #[Route('/{key}/+', name: 'new_root_node', methods: 'GET')]
	#[Template()]
    public function newRootNode(Request $request, $key)
    {
    	return [
    		'key' => $this->schema->getKey($key),
            'parentNodes' => new MultiCollection(null, null, [], null, null),
    		'rootKeys' => $this->schema->getRootKeys(),
    	];
    }

    #[Route('/{key}/{id}/{childKey}/+', name: 'new_child_node', methods: 'GET')]
	#[Template()]
    public function newChildNode(StorageConnection $storageConnection, $key, $id, $childKey)
    {
    	return [
    		'key' => $this->schema->getKey($key),
    		'childKey' => $this->schema->getKey($childKey),
            'node' => $storageConnection->getFetcher()->findNode($key, $id),
            'parentNodes' => $storageConnection->getFetcher()->findParentNodes($key, $id),
    		'rootKeys' => $this->schema->getRootKeys(),
    	];
    }

    #[Route('/{key}/{id}', name: 'show_node', methods: 'GET')]
	#[Template()]
    public function showNode(StorageConnection $storageConnection, $key, $id)
    {
    	return [
    		'key' => $this->schema->getKey($key),
            'node' => $storageConnection->getFetcher()->findNode($key, $id),
            'parentNodes' => $storageConnection->getFetcher()->findParentNodes($key, $id),
            'childNodes' => $storageConnection->getFetcher()->findNodeAllChildren($key, $id),
    		'rootKeys' => $this->schema->getRootKeys(),
    	];
    }

    #[Route('/{key}/{id}/_edit', name: 'edit_node', methods: 'GET')]
	#[Template()]
    public function editNode(StorageConnection $storageConnection, $key, $id)
    {
    	return [
    		'key' => $this->schema->getKey($key),
    		'node' => $storageConnection->getFetcher()->findNode($key, $id),
            'parentNodes' => $storageConnection->getFetcher()->findParentNodes($key, $id),
    		'rootKeys' => $this->schema->getRootKeys(),
    	];
    }

    #[Route('/{key}/{id}/-', name: 'delete_node', methods: 'POST')]
    public function deleteNode(StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, Session $session, Request $request, $key, $id)
    {
    	$lastParent = $storageConnection->getFetcher()->findNodeDirectParent($key, $id);

        try {
            $storageConnection->getCommander()->deleteNode($key, $id);
        } catch(DeletionBlockedException $e) {
            return new RedirectResponse($urlGen->generate('ask_delete_node', ['key' => $key, 'id' => $id]));
        }

		$then = $request->request->get('_then', null);

		$session->getFlashBag()->add('success', 'Node Deleted');


        if($then === 'list') {
            if($lastParent) {
                $args = array_merge($lastParent->pathArgs(), ['childKey' => $key]);
                return new RedirectResponse($urlGen->generate('list_child_nodes', $args));
            } else {
                return new RedirectResponse($urlGen->generate('list_root_nodes', ['key' => $key]));
            }
        } elseif($then === 'root_list') {
            return new RedirectResponse($urlGen->generate('list_root_nodes', ['key' => $key]));
        } elseif($then === 'parent') {
            if($lastParent) {
                return new RedirectResponse($urlGen->generate('show_node', $lastParent->pathArgs()));
            } else {
                return new RedirectResponse($urlGen->generate('hierarchy_root'));
            }
    	} else {
            return new RedirectResponse($urlGen->generate('list_root_nodes', ['key' => $key]));
        } 
    }

    #[Route('/{key}/{id}/-', name: 'ask_delete_node', methods: 'GET')]
	#[Template()]
    public function askDeleteNode(StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, $key, $id)
    {
        $deletionPlan = $storageConnection->getCommander()->getDeletionPlan($key, $id);

		return [
    		'key' => $this->schema->getKey($key),
    		'node' => $storageConnection->getFetcher()->findNode($key, $id),
            'parentNodes' => $storageConnection->getFetcher()->findParentNodes($key, $id),
    		'rootKeys' => $this->schema->getRootKeys(),
            'deletionPlan' => $deletionPlan,
    	];
    }

    #[Route('/{key}', name: 'create_node', methods: 'POST')]
    public function createNode(StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, Session $session, Request $request, $key)
    {
    	$key = $this->schema->getKey($key);
    	$scope = $request->request->get('scope', NULL);
    	$parent = $request->request->get('parent', NULL);
    	$newId = $storageConnection->getCommander()->createNode($key->getId(), $request->request->get('field', []), $scope, $parent);

		$then = $request->request->get('_then', null);
		
		$session->getFlashBag()->add('success', 'Node Created');

		if($then === 'form') {
            if($parent) {
                return new RedirectResponse($urlGen->generate('new_child_node', ['key' => $key->getId(), 'childKey' => $key->getId(), 'id' => $parent]));
            } elseif ($scope) {
                $parentKey = $key->getScopeKey()->getId();
                return new RedirectResponse($urlGen->generate('new_child_node', ['key' => $parentKey, 'childKey' => $key->getId(), 'id' => $scope]));
            } else {
                return new RedirectResponse($urlGen->generate('new_root_node', ['key' => $key->getId()]));
            }
        } elseif($then === 'root_form') {
            return new RedirectResponse($urlGen->generate('new_root_node', ['key' => $key->getId()]));
        } elseif($then === 'new') {
            return new RedirectResponse($urlGen->generate('show_node', ['key' => $key->getId(), 'id' => $newId]));
        } elseif($then === 'list') {
            if($parent) {
                return new RedirectResponse($urlGen->generate('list_child_nodes', ['key' => $key->getId(), 'childKey' => $key->getId(), 'id' => $parent]));
            } elseif ($scope) {
                $parentKey = $key->getScopeKey()->getId();
                return new RedirectResponse($urlGen->generate('list_child_nodes', ['key' => $parentKey, 'childKey' => $key->getId(), 'id' => $scope]));
            } else {
                return new RedirectResponse($urlGen->generate('list_root_nodes', ['key' => $key->getId()]));
            }
        } elseif($then === 'root_list') {
            return new RedirectResponse($urlGen->generate('list_root_nodes', ['key' => $key->getId()]));
        } else {
            if($parent) {
                return new RedirectResponse($urlGen->generate('show_node', ['key' => $key->getId(), 'id' => $parent]));
            } elseif ($scope) {
                $parentKey = $key->getScopeKey()->getId();
                return new RedirectResponse($urlGen->generate('show_node', ['key' => $parentKey, 'id' => $scope]));
            } else {
                return new RedirectResponse($urlGen->generate('hierarchy_root'));
            }
        }
    }

    #[Route('/{key}/{id}/_move', name: 'ask_move_node', methods: 'GET')]
    #[Template()]
    public function askMoveNode(StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, Session $session, Request $request, $key, $id)
    {
        $k = $this->schema->getKey($key);
        if(!$k->isNested()) {
            throw new NotFoundHttpException(sprintf('%s are not nested', $k->getLabel()->getPlural()));
        }

        return [
            'key' => $k,
            'moveTargets' => $storageConnection->getFetcher()->findNodeMoveTargets($key, $id),
            'node' => $storageConnection->getFetcher()->findNode($key, $id),
            'parentNodes' => $storageConnection->getFetcher()->findParentNodes($key, $id),
            'rootKeys' => $this->schema->getRootKeys(),
        ];
    }

    #[Route('/{key}/{id}/_move', name: 'move_node', methods: 'POST')]
    public function moveNode(StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, Session $session, Request $request, $key, $id)
    {
        $k = $this->schema->getKey($key);
        if(!$k->isNested()) {
            throw new NotFoundHttpException(sprintf('%s are not nested', $k->getLabel()->getPlural()));
        }

        list($scope, $parent) = explode('/', $request->request->get('target_scope-parent','/'), 2);
        
        $storageConnection->getCommander()->moveNode($key, $id, $scope?:null, $parent?:null);

        $session->getFlashBag()->add('success', 'Node Moved');


        $then = $request->request->get('_then', null);

        if($then === 'tree') {
            return new RedirectResponse($urlGen->generate('hierarchy_tree'));
        } else {
            return new RedirectResponse($urlGen->generate('ask_move_node', ['key' => $key, 'id' => $id]));
        }
    }

    #[Route('/{key}/{id}/_order', name: 'ask_order_node', methods: 'GET')]
    #[Template()]
    public function askOrderNode(StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, Session $session, Request $request, $key, $id)
    {
        $k = $this->schema->getKey($key);
        if(!$k->isOrdered()) {
            throw new NotFoundHttpException(sprintf('%s are not ordered', $k->getLabel()->getPlural()));
        }

        return [
            'key' => $this->schema->getKey($key),
            'orderTargets' => $storageConnection->getFetcher()->findNodeSiblings($key, $id),
            'node' => $storageConnection->getFetcher()->findNode($key, $id),
            'parentNodes' => $storageConnection->getFetcher()->findParentNodes($key, $id),
            'rootKeys' => $this->schema->getRootKeys(),
        ];
    }

    #[Route('/{key}/{id}/_order', name: 'order_node', methods: 'POST')]
    public function orderNode(StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, Session $session, Request $request, $key, $id)
    {

        $k = $this->schema->getKey($key);
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
            return new RedirectResponse($urlGen->generate('hierarchy_tree'));
        } elseif($then === 'list') {
            $directParent = $storageConnection->getFetcher()->findNodeDirectParent($key, $id);

            if($directParent) {
                return new RedirectResponse($urlGen->generate('list_child_nodes', ['key' => $directParent->getKey(), 'id' => $directParent->getId(), 'childKey' => $key]));

            } else {
                return new RedirectResponse($urlGen->generate('list_root_nodes', ['key' => $key]));
            }
        } elseif($then === 'root_list') {
            return new RedirectResponse($urlGen->generate('list_root_nodes', ['key' => $key]));
        } elseif($then === 'parent') {
            $directParent = $storageConnection->getFetcher()->findNodeDirectParent($key, $id);

            if($directParent) {
                return new RedirectResponse($urlGen->generate('show_node', ['key' => $directParent->getKey(), 'id' => $directParent->getId()]));

            } else {
                return new RedirectResponse($urlGen->generate('hierarchy_root'));
            }
        } else {
            return new RedirectResponse($urlGen->generate('ask_order_node', ['key' => $key, 'id' => $id]));
        }
    }

    #[Route('/{key}/{id}', name: 'update_node', methods: 'POST')]
    public function updateNode(StorageConnection $storageConnection, UrlGeneratorInterface $urlGen, Session $session, Request $request, $key, $id)
    {
		$storageConnection->getCommander()->updateNode($key, $id, $request->request->get('field', []));

		$then = $request->request->get('_then', null);

		$session->getFlashBag()->add('success', 'Node Updated');

		if($then === 'edit') {
    		return new RedirectResponse($urlGen->generate('edit_node', ['key' => $key, 'id' => $id]));
		} else {
    		return new RedirectResponse($urlGen->generate('show_node', ['key' => $key, 'id' => $id]));
		}
    }

    #[Route('/{key}', name: 'list_root_nodes', methods: 'GET')]
	#[Template()]
    public function listRootNodes(StorageConnection $storageConnection, $key)
    {
    	return [
    		'key' => $this->schema->getKey($key),
    		'nodeCollection' => $storageConnection->getFetcher()->findRootNodes($key),
            'parentNodes' => new MultiCollection(null, null, [], null, null),
    		'rootKeys' => $this->schema->getRootKeys(),
    	];
    }

    #[Route('/{key}/{id}/{childKey}', name: 'list_child_nodes', methods: 'GET')]
	#[Template()]
    public function listChildNodes(StorageConnection $storageConnection, $key, $id, $childKey)
    {
    	return [
    		'key' => $this->schema->getKey($key),
    		'childKey' => $this->schema->getKey($childKey),
            'node' => $storageConnection->getFetcher()->findNode($key, $id),
            'parentNodes' => $storageConnection->getFetcher()->findParentNodes($key, $id),
            'nodeCollection' => $storageConnection->getFetcher()->findNodeChildren($key, $id, $childKey),
    		'rootKeys' => $this->schema->getRootKeys(),
    	];
    }

}