<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
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

    #[Route('/setup', name: 'show_hierarchy_setup', methods: 'GET')]
	#[Template()]
    public function showSetup()
    {
    	return ['schemaSQL' => $this->repo->showSchema()];
    }

    #[Route('/setup', name: 'hierarchy_setup', methods: 'POST')]
    public function setup(UrlGeneratorInterface $urlGen)
    {
    	$this->repo->createSchema();

    	return new RedirectResponse($urlGen->generate('hierarchy_root'));
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
    	];
    }

    #[Route('/{key}/{id}/delete', name: 'delete_node', methods: 'POST')]
    public function deleteNode(UrlGeneratorInterface $urlGen, $key, $id)
    {
    	$lastParent = $this->repo->loadNodesDirectParent($key, $id);

		$this->repo->deleteNode($key, $id);

		if($lastParent) {
    		return new RedirectResponse($urlGen->generate('show_node', $lastParent));
    	} else {
    		return new RedirectResponse($urlGen->generate('hierarchy_root'));
    	}
    }

    #[Route('/{key}', name: 'create_node', methods: 'POST')]
    public function createNode(UrlGeneratorInterface $urlGen, Request $request, $key)
    {
    	$scope = $request->request->get('scope', NULL);
    	$parent = $request->request->get('parent', NULL);
    	$this->repo->createNode($key, $request->request->get('field', []), $scope, $parent);

    	if($parent) {
    		return new RedirectResponse($urlGen->generate('show_node', ['key' => $key, 'id' => $parent]));
    	} elseif($scope) {
    		$parentKey = $this->repo->getParentKey($key);
    		return new RedirectResponse($urlGen->generate('show_node', ['key' => $parentKey, 'id' => $scope]));
    	} else {
    		return new RedirectResponse($urlGen->generate('hierarchy_root'));
    	}
    }

    #[Route('/{key}/{id}/move', name: 'move_node', methods: 'POST')]
    public function moveNode(UrlGeneratorInterface $urlGen, Request $request, $key, $id)
    {
    	[$scope,$parent] = explode('/', $request->request->get('target_scope-parent','/'), 2);

		$this->repo->moveNode($key, $id, $scope?:null, $parent?:null);

    	return new RedirectResponse($urlGen->generate('show_node', ['key' => $key, 'id' => $id]));
    }

    #[Route('/{key}/{id}', name: 'update_node', methods: 'POST')]
    public function updateNode(UrlGeneratorInterface $urlGen, Request $request, $key, $id)
    {
		$this->repo->updateNode($key, $id, $request->request->get('field', []));

    	return new RedirectResponse($urlGen->generate('show_node', ['key' => $key, 'id' => $id]));
    }

    #[Route('/{key}/normalize_order', name: 'normalize_order', methods: 'POST')]
    public function normalizeOrder()
    {
    }

}