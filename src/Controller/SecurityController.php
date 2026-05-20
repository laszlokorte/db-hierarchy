<?php

namespace App\Controller;

use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController
{
    /**
     * @return array<string,mixed>
     */
    #[Route('/_login', name: 'security_login', methods: 'GET|POST', priority: 1000)]
    #[Template('security/login_form.html.twig')]
    public function loginForm(Request $request, AuthenticationUtils $authenticationUtils): array
    {
        return [
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'lastUsername' => $authenticationUtils->getLastUsername(),
        ];
    }

    #[Route('/_logout', name: 'security_logout', methods: 'GET', priority: 1000)]
    public function logoutForm(Request $request, AuthenticationUtils $authenticationUtils): void
    {
    }
}
