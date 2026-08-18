<?php

namespace App\Controller;

use App\Post\PostRepository;
use App\Settings\SiteSettings;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class UndoPostController
{
    public function __construct(
        private readonly PostRepository $posts,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly SiteSettings $siteSettings,
        private readonly Environment $twig,
    ) {
    }

    #[Route('/posts/{postId<\d+>}/undo', name: 'app_post_undo', methods: ['POST'])]
    public function __invoke(Request $request, int $postId): Response
    {
        $session = $request->getSession();
        $undo = $session->get('post_undo');
        $token = $request->request->getString('_token');
        if (
            !$this->siteSettings->undoEnabled()
            || !is_array($undo)
            || ($undo['post_id'] ?? null) !== $postId
            || !is_int($undo['expires_at'] ?? null)
            || $undo['expires_at'] < time()
            || !is_string($undo['token'] ?? null)
            || !hash_equals($undo['token'], $token)
        ) {
            throw new AccessDeniedHttpException('該当記事の消去は許可されていません。');
        }

        if (!$this->posts->delete($postId)) {
            $session->remove('post_undo');
            throw new NotFoundHttpException('該当記事は見つかりませんでした。');
        }
        $session->remove('post_undo');

        return new Response($this->twig->render('bbs/undo_complete.html.twig', [
            'app_title' => $this->siteSettings->title(),
            'return_to' => $this->urlGenerator->generate('app_hello'),
        ]));
    }
}
