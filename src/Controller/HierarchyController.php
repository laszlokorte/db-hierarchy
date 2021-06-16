<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use App\Hierarchy\Repository;

class HierarchyController {

	public function __construct(Repository $hierarchyRepository) {
		$this->repo = $hierarchyRepository;
	}
	
	#[Route('/', name: 'hierarchy_root', methods: 'GET')]
	#[Template()]
    public function root()
    {
    	return [
    		'rootKeys' => $this->repo->getRootKeys(),
    		'allFields' => $this->repo->getAllFields(),
    		'rootNodes' => $this->repo->loadRootNodes(),
    	];
    }

    #[Route('/_full-tree', name: 'hierarchy_tree', methods: 'GET')]
	#[Template()]
    public function tree()
    {
    	return [
    		'rootKeys' => $this->repo->getRootKeys(),
    		'allFields' => $this->repo->getAllFields(),
    		'rootNodes' => $this->repo->loadRootNodes(),
    		'tree' => $this->repo->loadHierarchy(),
    	];
    }

    #[Route('/_setup', name: 'show_hierarchy_setup', methods: 'GET')]
	#[Template()]
    public function showSetup()
    {
    	return [
    		'rootKeys' => $this->repo->getRootKeys(),
    		'schemaSQL' => $this->repo->showSchema()
    	];
    }

    #[Route('/_setup', name: 'hierarchy_setup', methods: 'POST')]
    public function setup(UrlGeneratorInterface $urlGen)
    {
    	$this->repo->createSchema();

    	return new RedirectResponse($urlGen->generate('hierarchy_root'));
    }

    #[Route('/_defects', name: 'show_hierarchy_defects', methods: 'GET')]
	#[Template()]
    public function showDefects()
    {
    	return [
    		'rootKeys' => $this->repo->getRootKeys(),
    		'closureDefects' => $this->repo->loadAllClosureDefects(),
    		'orderDefects' => $this->repo->loadAllRowOrder(),
    	];
    }

    #[Route('/_defects', name: 'repair_hierarchy_defects', methods: 'POST')]
    public function repairDefects(UrlGeneratorInterface $urlGen)
    {
    	$this->repo->repairAllClosureDefects(1);

    	return new RedirectResponse($urlGen->generate('show_hierarchy_defects'));
    }

    #[Route('/_defects', name: 'repair_hierarchy_key_defects', methods: 'POST')]
    public function repairKeyDefects(UrlGeneratorInterface $urlGen)
    {
    	$this->repo->repairKeyClosureDefects($key, 1);

    	return new RedirectResponse($urlGen->generate('show_hierarchy_defects'));
    }

    #[Route('/_normalize_order/{key}', name: 'normalize_key_order', methods: 'POST')]
    public function normalizeKeyOrder(UrlGeneratorInterface $urlGen, $key)
    {
    	$this->repo->normalizedKeyAllRowOrder($key);

    	return new RedirectResponse($urlGen->generate('show_hierarchy_defects'));
    }

    #[Route('/_normalize_order', name: 'normalize_all_order', methods: 'POST')]
    public function normalizeAllOrder(UrlGeneratorInterface $urlGen)
    {
    	$this->repo->normalizedAllRowOrder();

    	return new RedirectResponse($urlGen->generate('show_hierarchy_defects'));
    }

    #[Route('/{key}({field})/{id}', name: 'show_node_field', methods: 'GET')]
	#[Template()]
    public function showNodeField($key, $id, $field)
    {
    	$value = $this->repo->loadNodeField($key, $id, $field);

    	return new JsonResponse([
    		'key' => $key,
    		'id' => $id,
    		'field' => $field,
    		'value' => $value,
    	]);
    }

    #[Route('/{key}/+', name: 'new_root_node', methods: 'GET')]
	#[Template()]
    public function newRootNode(Request $request, $key)
    {
    	return [
    		'key' => $key,
    		'fields' => $this->repo->getFields($key),
    		//'node' => $this->repo->loadNode($key, $id),
    		'rootKeys' => $this->repo->getRootKeys(),
    	];
    }

    #[Route('/{key}/{id}/{childKey}/+', name: 'new_child_node', methods: 'GET')]
	#[Template()]
    public function newChildNode($key, $id, $childKey)
    {
    	return [
    		'key' => $key,
    		'id' => $id,
    		'childKey' => $childKey,
    		'moveTargets' => $this->repo->loadMoveTargets($key, $id),
    		'fields' => $this->repo->getFields($key),
    		'childFields' => $this->repo->getFields($childKey),
    		'node' => $this->repo->loadNode($key, $id),
    		'childKeys' => $this->repo->getChildKeys($key),
    		'rootKeys' => $this->repo->getRootKeys(),
    	];
    }

    #[Route('/{key}/{id}', name: 'show_node', methods: 'GET')]
	#[Template()]
    public function showNode($key, $id)
    {
    	return [
    		'key' => $key,
    		'id' => $id,
    		'moveTargets' => $this->repo->loadMoveTargets($key, $id),
    		'fields' => $this->repo->getFields($key),
    		'childFields' => $this->repo->getChildFields($key),
    		'node' => $this->repo->loadNode($key, $id),
    		'childKeys' => $this->repo->getChildKeys($key),
    		'rootKeys' => $this->repo->getRootKeys(),
    	];
    }

    #[Route('/{key}/{id}/_edit', name: 'edit_node', methods: 'GET')]
	#[Template()]
    public function editNode($key, $id)
    {
    	return [
    		'key' => $key,
    		'id' => $id,
    		'fields' => $this->repo->getFields($key),
    		'node' => $this->repo->loadNode($key, $id),
    		'rootKeys' => $this->repo->getRootKeys(),
    	];
    }

    #[Route('/{key}/{id}/-', name: 'delete_node', methods: 'POST')]
    public function deleteNode(UrlGeneratorInterface $urlGen, Request $request, $key, $id)
    {
    	$lastParent = $this->repo->loadNodesDirectParent($key, $id);

		$this->repo->deleteNode($key, $id);

		if($lastParent) {
			$then = $request->request->get('_then', null);
			if($then === 'list') {
				$args = $lastParent;
				$args['childKey'] = $key;
    			return new RedirectResponse($urlGen->generate('list_child_nodes', $args));
			} else {
    			return new RedirectResponse($urlGen->generate('show_node', $lastParent));
			}
    	} else {
    		return new RedirectResponse($urlGen->generate('list_root_nodes', ['key' => $key]));
    	}
    }

    #[Route('/{key}/{id}/-', name: 'ask_delete_node', methods: 'GET')]
	#[Template()]
    public function askDeleteNode(UrlGeneratorInterface $urlGen, $key, $id)
    {
    	$lastParent = $this->repo->loadNodesDirectParent($key, $id);

		return [
    		'key' => $key,
    		'id' => $id,
    		'node' => $this->repo->loadNode($key, $id),
    		'rootKeys' => $this->repo->getRootKeys(),
    	];
    }

    #[Route('/{key}', name: 'create_node', methods: 'POST')]
    public function createNode(UrlGeneratorInterface $urlGen, Request $request, $key)
    {
    	$scope = $request->request->get('scope', NULL);
    	$parent = $request->request->get('parent', NULL);
    	$newId = $this->repo->createNode($key, $request->request->get('field', []), $scope, $parent);

		$then = $request->request->get('_then', null);

		if($then === 'new') {
			return new RedirectResponse($urlGen->generate('show_node', ['key' => $key, 'id' => $newId]));
		}

    	if($parent) {
			if($then === 'list') {
    			return new RedirectResponse($urlGen->generate('list_child_nodes', ['key' => $key, 'childKey' => $key, 'id' => $parent]));
			} else {
    			return new RedirectResponse($urlGen->generate('show_node', ['key' => $key, 'id' => $parent]));
			}
    	} elseif($scope) {
    		$parentKey = $this->repo->getParentKey($key);
    		if($then === 'list') {
    			return new RedirectResponse($urlGen->generate('list_child_nodes', ['key' => $parentKey, 'childKey' => $key, 'id' => $scope]));
			} else {
    			return new RedirectResponse($urlGen->generate('show_node', ['key' => $parentKey, 'id' => $scope]));
			}
    	} else {
    		return new RedirectResponse($urlGen->generate('list_root_nodes', ['key' => $key]));
    	}
    }

    #[Route('/{key}/{id}/_move', name: 'move_node', methods: 'POST')]
    public function moveNode(UrlGeneratorInterface $urlGen, Request $request, $key, $id)
    {
    	$target = explode('/', $request->request->get('target_scope-parent','/'), 2);
    	$scope = $target[0]??null;
    	$parent = $target[1]??null;

		$this->repo->moveNode($key, $id, $scope?:null, $parent?:null);

    	return new RedirectResponse($urlGen->generate('show_node', ['key' => $key, 'id' => $id]));
    }

    #[Route('/{key}/{id}', name: 'update_node', methods: 'POST')]
    public function updateNode(UrlGeneratorInterface $urlGen, Request $request, $key, $id)
    {
		$this->repo->updateNode($key, $id, $request->request->get('field', []));

		$then = $request->request->get('_then', null);

		if($then === 'edit') {
    		return new RedirectResponse($urlGen->generate('edit_node', ['key' => $key, 'id' => $id]));
		} else {
    		return new RedirectResponse($urlGen->generate('show_node', ['key' => $key, 'id' => $id]));
		}
    }

    #[Route('_all/{key}.json', name: 'list_all_nodes', methods: 'GET')]
	#[Template()]
    public function listAllNodes($key)
    {
    	return new JsonResponse([
    		$key => $this->repo->loadAllKeyNodes($key)
    	]);
    }

    #[Route('/{key}', name: 'list_root_nodes', methods: 'GET')]
	#[Template()]
    public function listRootNodes($key)
    {
    	return [
    		'key' => $key,
    		'fields' => $this->repo->getFields($key),
    		'nodes' => $this->repo->loadRootKeyNodes($key),
    		'rootKeys' => $this->repo->getRootKeys(),
    	];
    }

    #[Route('/{key}/{id}/{childKey}', name: 'list_child_nodes', methods: 'GET')]
	#[Template()]
    public function listChildNodes($key, $id, $childKey)
    {
    	return [
    		'key' => $key,
    		'childKey' => $childKey,
    		'node' => $this->repo->loadNode($key, $id),
    		'childFields' => $this->repo->getFields($childKey),
    		'childNodes' => $this->repo->loadChildKeyNodes($key, $id, $childKey),
    		'rootKeys' => $this->repo->getRootKeys(),
    	];
    }

}