<?php

namespace App\Controller;

use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use App\Hierarchy\Storage\Relational\StorageConnection;
use App\Hierarchy\Schema\Hierarchy;

class AccountController {
    /**
     * @return array<string,Hierarchy>
     */
    #[Route('/_account', name: 'account_show', methods: 'GET|POST', priority: 1000, defaults: ['hierarchySlug' => 'system'])]
	#[Template('/account/show.html.twig')]
	public function show(Request $request, Session $session, UrlGeneratorInterface $urlGen, Hierarchy $hierarchy, StorageConnection $storageConnection): array {
		return [
            'hierarchy' => $hierarchy,
        ];
	}
}
