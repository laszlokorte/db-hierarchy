<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Hierarchy\Schema\Hierarchy;

use Twig\Environment;

class AssetController {

	#[Route('/favicon-{hierarchySlug}.svg', name: 'hierarchy_favicon_svg', methods: 'GET', priority: 1000, defaults: ['hierarchy' => 'system'])]
    #[Route('/favicon.svg', name: 'favicon_svg', methods: 'GET', priority: 1000, defaults: ['hierarchy' => 'system'])]
    #[ParamConverter('schema')]
	#[Cache(expires: "tomorrow", public: true)]
	public function favicon(Request $request, Environment $twig, Hierarchy $schema) {
		$response = new Response($twig->render('asset/favicon.svg.twig', [
			'color' => $schema->getLabel()->getColor(),
		]), 200, [
			'Content-Type' => 'image/svg+xml',
		]);

		$response->setEtag(md5($response->getContent()));
        $response->setPublic();
        $response->isNotModified($request);

        return $response;
	}

	#[Route('/favicon.ico', name: 'favicon_ico', methods: 'GET', priority: 1000)]
	public function faviconFallback(UrlGeneratorInterface $urlGen) {
		return new RedirectResponse($urlGen->generate('favicon_svg'));
	}
} 