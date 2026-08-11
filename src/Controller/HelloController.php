<?php

namespace App\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HelloController
{
    public function __construct(
        #[Autowire(param: 'app.title')]
        private readonly string $appTitle,
    ) {
    }

    #[Route('/', name: 'app_hello')]
    public function __invoke(): Response
    {
        $title = htmlspecialchars($this->appTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return new Response(<<<HTML
            <!DOCTYPE html>
            <html lang="ja">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1">
                    <title>{$title}</title>
                </head>
                <body>
                    <h1>Hello World!</h1>
                    <p>{$title}</p>
                </body>
            </html>
            HTML);
    }
}
