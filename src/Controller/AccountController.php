<?php

namespace App\Controller;

use App\Form\Type\Account\PasswordForm;
use App\Hierarchy\Schema\Hierarchy;
use App\Hierarchy\Storage\Relational\StorageConnection;
use App\Security\HierarchyAccountUser;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class AccountController
{
    /**
     * @return array<string,Hierarchy>
     */
    #[Route('/_account', name: 'account_show', methods: 'GET|POST', priority: 1000, defaults: ['hierarchySlug' => 'system'])]
    #[Template('/account/show.html.twig')]
    public function show(Request $request, Session $session, UrlGeneratorInterface $urlGen, FormFactoryInterface $formFactory, Hierarchy $hierarchy, StorageConnection $storageConnection, #[CurrentUser] ?HierarchyAccountUser $account): array|RedirectResponse
    {
        $passwordForm = $formFactory->create(
            PasswordForm::class,
            [
            ],
            [
                'action' => $urlGen->generate('account_show'),
                'method' => 'POST',
            ]
        );

        $passwordForm->handleRequest($request);

        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            return new RedirectResponse($urlGen->generate('account_show'));
        }

        return [
            'hierarchy' => $hierarchy,
            'account' => $account,
            'passwordForm' => $passwordForm->createView(),
        ];
    }
}
