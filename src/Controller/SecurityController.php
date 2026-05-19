<?php

namespace App\Controller;

use Symfony\Bridge\Twig\Attribute\Template as SymfonyTemplate;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

class SecurityController {

	#[Route('/_login', name: 'security_login', methods: 'GET|POST', priority: 1000)]
	#[SymfonyTemplate()]
	public function loginForm(Request $request, AuthenticationUtils $authenticationUtils) {
		return [
			'error' => $authenticationUtils->getLastAuthenticationError(),
		    'lastUsername' => $authenticationUtils->getLastUsername(),
		];
	}

	#[Route('/_logout', name: 'security_logout', methods: 'GET', priority: 1000)]
	public function logoutForm(Request $request, AuthenticationUtils $authenticationUtils) {
	}
}
