<?php

namespace App\Controller;

use App\Post\PostRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Cookie;
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
        $displayCount = max(1, min(1000, $request->request->getInt('display_count', 40)));
        $autoLink = $request->request->getBoolean('auto_link');
        $message = $request->request->getString('content');
        if (trim($message) === '') {
            return $this->redirectToBbs($request, $displayCount, $autoLink);
        }

        $replyTo = $request->request->getInt('reply_to') ?: null;
        $threadId = null;
        if ($replyTo !== null) {
            foreach ($this->posts->all() as $post) {
                if ($post['post_id'] === $replyTo) {
                    $threadId = $post['thread_id'];
                    break;
                }
            }
            if ($threadId === null) {
                return $this->redirectToBbs($request, $displayCount, $autoLink);
            }
        }

        $this->posts->append([
            'author' => $request->request->getString('author'),
            'email' => $request->request->getString('email'),
            'title' => $request->request->getString('title'),
            'message' => $message,
            'auto_link' => $autoLink,
            'host' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'thread_id' => $threadId,
            'reply_to' => $replyTo,
        ]);

        return $this->redirectToBbs($request, $displayCount, $autoLink);
    }

    private function redirectToBbs(Request $request, int $displayCount, bool $autoLink): RedirectResponse
    {
        $returnTo = $request->request->getString('return_to');
        $isTree = preg_match('#^/tree(?:/\d+)?$#D', $returnTo) === 1;
        $parameters = !$isTree && $displayCount !== 40 ? ['display_count' => $displayCount] : [];
        $response = new RedirectResponse(
            $isTree ? $returnTo : $this->urlGenerator->generate('app_hello', $parameters),
            Response::HTTP_SEE_OTHER,
        );
        $expires = new \DateTimeImmutable('+1 year');
        $response->headers->setCookie(new Cookie(
            'bbs_display_count',
            (string) $displayCount,
            $expires,
            secure: $request->isSecure(),
            sameSite: Cookie::SAMESITE_LAX,
        ));
        $response->headers->setCookie(new Cookie(
            'bbs_auto_link',
            $autoLink ? '1' : '0',
            $expires,
            secure: $request->isSecure(),
            sameSite: Cookie::SAMESITE_LAX,
        ));

        return $response;
    }
}
