<?php

namespace App\Controller;

use App\Log\PostLog;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SubmitController
{
    public function __construct(
        private readonly PostLog $postLog,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/submit', name: 'app_submit', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $this->postLog->append([
            'author' => $request->request->getString('author'),
            'email' => $request->request->getString('email'),
            'title' => $request->request->getString('title'),
            'message' => $request->request->getString('content'),
            'host' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
        ]);

        return new RedirectResponse(
            $this->urlGenerator->generate('app_hello'),
            Response::HTTP_SEE_OTHER,
        );
    }
}
