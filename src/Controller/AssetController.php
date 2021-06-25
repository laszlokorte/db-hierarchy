<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Hierarchy\Schema\SchemaRoot;

use Twig\Environment;

class AssetController {

	#[Route('/favicon.svg', name: 'favicon_svg', methods: 'GET', priority: 1000)]
	public function favicon(Environment $twig, SchemaRoot $schema) {
		return new Response($twig->render('asset/favicon.svg.twig', [
			'color' => $schema->getLabel()->getColor(),
		]), 200, [
			'Content-Type' => 'image/svg+xml',
		]);
	}

	#[Route('/favicon.ico', name: 'favicon_ico', methods: 'GET', priority: 1000)]
	public function faviconFallback(UrlGeneratorInterface $urlGen) {
		return new RedirectResponse($urlGen->generate('favicon_svg'));
	}
} 