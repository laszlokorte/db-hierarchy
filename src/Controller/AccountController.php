<?php

namespace App\Controller;

use Symfony\Bridge\Twig\Attribute\Template as SymfonyTemplate;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Route as Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;


use App\Hierarchy\Storage\Relational\StorageConnection;
use App\Hierarchy\Schema\Hierarchy;

class AccountController {

	#[Route('/_account', name: 'account_show', methods: 'GET|POST', priority: 1000, defaults: ['hierarchySlug' => 'system'])]
	#[SymfonyTemplate()]
	public function show(Request $request, Session $session, UrlGeneratorInterface $urlGen, Hierarchy $hierarchy, StorageConnection $storageConnection) {
		return [
            'hierarchy' => $hierarchy,
        ];
	}
}
