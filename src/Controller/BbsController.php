<?php

namespace App\Controller;

use App\Post\PostRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

final class BbsController
{
    public function __construct(
        #[Autowire(param: 'app.title')]
        private readonly string $appTitle,
        private readonly Environment $twig,
        private readonly PostRepository $posts,
    ) {
    }

    #[Route('/', name: 'app_hello')]
    public function __invoke(): Response
    {
        return new Response($this->twig->render('bbs/index.html.twig', [
            'app_title' => $this->appTitle,
            'posts' => $this->posts->recent(40),
        ]));
    }

    #[Route('/tree/{threadId<\d+>}', name: 'app_thread_tree', methods: ['GET'])]
    public function tree(int $threadId): Response
    {
        $thread = array_values(array_filter(
            $this->posts->all(),
            static fn (array $post): bool => $post['thread_id'] === $threadId,
        ));
        if ($thread === []) {
            throw new NotFoundHttpException('スレッドが見つかりません。');
        }

        usort($thread, static fn (array $left, array $right): int => $left['post_id'] <=> $right['post_id']);
        $updatedAt = $thread[array_key_last($thread)]['posted_at'];
        $postIds = array_fill_keys(array_column($thread, 'post_id'), true);
        $parentByPostId = [];
        foreach ($thread as $post) {
            $parentByPostId[$post['post_id']] = $post['reply_to'];
        }
        $children = [];
        foreach ($thread as $post) {
            $post['author'] = trim($post['author'], " \n\r\t\v\0　");
            $post['message'] = $this->treeMessage($post['message']);
            $parent = $post['reply_to'];
            $hasValidParent = $parent !== null
                && isset($postIds[$parent])
                && !$this->hasReplyCycle($post['post_id'], $parentByPostId);
            $key = $hasValidParent ? (string) $parent : 'root';
            $children[$key][] = $post;
        }

        return new Response($this->twig->render('bbs/tree.html.twig', [
            'app_title' => $this->appTitle,
            'thread_id' => $threadId,
            'updated_at' => $updatedAt,
            'children' => $children,
        ]));
    }

    /** @param array<int, int|null> $parentByPostId */
    private function hasReplyCycle(int $postId, array $parentByPostId): bool
    {
        $current = $postId;
        $visited = [];
        while (array_key_exists($current, $parentByPostId)) {
            if (isset($visited[$current])) {
                return true;
            }
            $visited[$current] = true;
            $parent = $parentByPostId[$current];
            if ($parent === null) {
                return false;
            }
            $current = $parent;
        }

        return false;
    }

    private function treeMessage(string $message): string
    {
        $lines = preg_split('/\R/u', $message);
        if ($lines === false) {
            return trim($message);
        }
        $lines = array_filter(
            $lines,
            static fn (string $line): bool => preg_match('/^[>＞]/u', $line) !== 1,
        );

        return trim(implode("\n", $lines));
    }
}
