<?php

namespace App\Controller;

use App\Post\PostRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SubmitController
{
    public function __construct(
        private readonly PostRepository $posts,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/submit', name: 'app_submit', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $message = $request->request->getString('content');
        if (trim($message) === '') {
            return $this->redirectToBbs();
        }

        $this->posts->append([
            'author' => $request->request->getString('author'),
            'email' => $request->request->getString('email'),
            'title' => $request->request->getString('title'),
            'message' => $message,
            'host' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
        ]);

        return $this->redirectToBbs();
    }

    private function redirectToBbs(): RedirectResponse
    {
        return new RedirectResponse(
            $this->urlGenerator->generate('app_hello'),
            Response::HTTP_SEE_OTHER,
        );
    }
}
