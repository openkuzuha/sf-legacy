<?php

namespace App\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

final class HelloController
{
    public function __construct(
        #[Autowire(param: 'app.title')]
        private readonly string $appTitle,
        private readonly Environment $twig,
    ) {
    }

    #[Route('/', name: 'app_hello')]
    public function __invoke(): Response
    {
        return new Response($this->twig->render('hello/index.html.twig', [
            'app_title' => $this->appTitle,
        ]));
    }
}
