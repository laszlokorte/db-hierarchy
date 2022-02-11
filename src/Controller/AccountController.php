<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;


use App\Hierarchy\Storage\Relational\StorageConnection;
use App\Hierarchy\Schema\Hierarchy;

class AccountController {

	#[Route('/_account', name: 'account_show', methods: 'GET|POST', priority: 1000, defaults: ['hierarchySlug' => 'system'])]
    #[ParamConverter('storageConnection')]
    #[ParamConverter('hierarchy')]
	#[Template()]
	public function show(Request $request, Session $session, UrlGeneratorInterface $urlGen, Hierarchy $hierarchy, StorageConnection $storageConnection) {
		return [
            'hierarchy' => $hierarchy,
        ];
	}
} 