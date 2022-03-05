<?php

namespace App\Response;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RedirectHandler {
    public function __construct(RequestStack $requestStack, UrlGeneratorInterface $urlGen) {
        $this->requestStack = $requestStack;
        $this->urlGen = $urlGen;
    }

    private function setFlash(string $type, string $message) {
        $this->requestStack->getSession()->getFlashBag()->add($type, $message);
    }

	public function redirectAfterUpdate() {
		if($then === 'root') {
            return new RedirectResponse($urlGen->generate('hierarchy_root', ['hierarchySlug' => $hierarchy->getSlug()]));
        } elseif($then === 'list') {
            $lastParent = $storageConnection->getQueryService()->findNodeDirectParent($key->getId(), $nodeId);
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

	public function redirectAfterCreateChild() {
		if($then === 'form') {
            if($parent) {
                return new RedirectResponse($urlGen->generate('new_child_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'childKeyId' => $childKey->getId(), 'nodeId' => $parent]));
            } elseif ($scope) {
                $parentKey = $key->getId();
                return new RedirectResponse($urlGen->generate('new_child_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $parentKey, 'childKeyId' => $childKey->getId(), 'nodeId' => $scope]));
            } else {
                return new RedirectResponse($urlGen->generate('new_root_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $childKey->getId()]));
            }
        } elseif($then === 'root_form') {
            return new RedirectResponse($urlGen->generate('new_root_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $childKey->getId()]));
        } elseif($then === 'new') {
            return new RedirectResponse($urlGen->generate('show_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'nodeId' => $newId]));
        } elseif($then === 'list') {
            if($parent) {
                return new RedirectResponse($urlGen->generate('list_child_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'childKeyId' => $key->getId(), 'nodeId' => $parent]));
            } elseif ($scope) {
                $parentKey = $key->getId();
                return new RedirectResponse($urlGen->generate('list_child_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $parentKey, 'childKeyId' => $childKey->getId(), 'nodeId' => $scope]));
            } else {
                return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId()]));
            }
        } elseif($then === 'root_list') {
            return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId()]));
        } else {
            if($parent) {
                return new RedirectResponse($urlGen->generate('show_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'nodeId' => $parent]));
            } elseif ($scope) {
                $parentKey = $key->getId();
                return new RedirectResponse($urlGen->generate('show_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $parentKey, 'nodeId' => $scope]));
            } else {
                return new RedirectResponse($urlGen->generate('hierarchy_root', ['hierarchySlug' => $hierarchy->getSlug(), 'hierarchySlug' => $hierarchy->getSlug()]));
            }
        }
	}

	public function redirectAfterOrder() {
		if($then === 'tree') {
            return new RedirectResponse($urlGen->generate('hierarchy_tree', ['hierarchySlug' => $hierarchy->getSlug()]));
        } elseif($then === 'list') {
            $directParent = $storageConnection->getQueryService()->findNodeDirectParent($key->getId(), $nodeId);

            if($directParent) {
                return new RedirectResponse($urlGen->generate('list_child_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $directParent->getKey(), 'nodeId' => $directParent->getId(), 'childKeyId' => $key->getId()]));

            } else {
                return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId()]));
            }
        } elseif($then === 'root_list') {
            return new RedirectResponse($urlGen->generate('list_root_nodes', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId()]));
        } elseif($then === 'parent') {
            $directParent = $storageConnection->getQueryService()->findNodeDirectParent($key->getId(), $nodeId);

            if($directParent) {
                return new RedirectResponse($urlGen->generate('show_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $directParent->getKey(), 'nodeId' => $directParent->getId()]));

            } else {
                return new RedirectResponse($urlGen->generate('hierarchy_root', ['hierarchySlug' => $hierarchy->getSlug()]));
            }
        } else {
            return new RedirectResponse($urlGen->generate('ask_order_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'nodeId' => $nodeId]));
        }
	}

	public function redirectAfterMove() {
		if($then === 'tree') {
            return new RedirectResponse($urlGen->generate('hierarchy_tree', ['hierarchySlug' => $hierarchy->getSlug()]));
        } else {
            return new RedirectResponse($urlGen->generate('ask_move_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'nodeId' => $nodeId]));
        }
	}

	public function redirectAfterDeletion() {
		if($failed) {
			return new RedirectResponse($urlGen->generate('ask_delete_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'nodeId' => $nodeId]));
		}

		if($preventCascade) {
			return new RedirectResponse($urlGen->generate('ask_delete_node', ['hierarchySlug' => $hierarchy->getSlug(), 'keyId' => $key->getId(), 'nodeId' => $nodeId]));
		}

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

	public function redirectAfterRepair() {
		return new RedirectResponse($urlGen->generate('show_health', ['hierarchySlug' => $hierarchy->getSlug(), 'subHierarchySlug' => $subHierarchy->getSlug()]));
	}

	public function redirectAfterUninstall() {
		return new RedirectResponse($urlGen->generate('show_system_installer', ['hierarchySlug' => $hierarchy->getSlug(), 'subHierarchySlug' => $subHierarchy->getSlug()]));
	}

	public function redirectAfterInstall() {
		return new RedirectResponse($urlGen->generate('show_system_installer', ['hierarchySlug' => $hierarchy->getSlug(), 'subHierarchySlug' => $subHierarchy->getSlug()]));
	}
}