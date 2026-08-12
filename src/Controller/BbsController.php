<?php

namespace App\Controller;

use App\PageView\PageViewCounter;
use App\Post\DailyPostArchive;
use App\Post\PostRepository;
use App\Visitor\VisitorCounter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

final class BbsController
{
    private const int DEFAULT_PAGE_SIZE = 40;

    private const int MAX_PAGE_SIZE = 1000;

    public function __construct(
        #[Autowire(param: 'app.title')]
        private readonly string $appTitle,
        private readonly Environment $twig,
        private readonly PostRepository $posts,
        private readonly DailyPostArchive $archive,
        private readonly VisitorCounter $visitorCounter,
        private readonly PageViewCounter $pageViewCounter,
        #[Autowire(param: 'app.page_view_started_at')]
        private readonly string $pageViewStartedAt,
        #[Autowire(param: 'app.central_post_limit')]
        private readonly int $centralPostLimit,
    ) {
    }

    #[Route('/', name: 'app_hello')]
    public function __invoke(Request $request): Response
    {
        $storedPageSize = $request->cookies->getInt('bbs_display_count', self::DEFAULT_PAGE_SIZE);
        $pageSize = max(1, min(
            self::MAX_PAGE_SIZE,
            $request->query->has('display_count')
                ? $request->query->getInt('display_count')
                : $storedPageSize,
        ));
        $beforePostId = $request->query->getInt('before');
        $startPosition = 1;
        $allPosts = null;
        if ($beforePostId > 0) {
            $allPosts = $this->posts->all();
            $candidates = array_values(array_filter(
                $allPosts,
                static fn (array $post): bool => $post['post_id'] < $beforePostId,
            ));
            $startPosition = count($allPosts) - count($candidates) + 1;
        } else {
            $candidates = $this->posts->recent($pageSize + 1);
        }
        $hasMore = count($candidates) > $pageSize;
        $posts = array_slice($candidates, 0, $pageSize);
        $nextBefore = null;
        if ($hasMore && $posts !== []) {
            $nextBefore = $posts[count($posts) - 1]['post_id'];
        }

        $hasReplies = false;
        foreach ($posts as $post) {
            if ($post['reply_to'] !== null) {
                $hasReplies = true;
                break;
            }
        }
        if ($hasReplies) {
            $allPosts ??= $this->posts->all();
            $postsById = [];
            foreach ($allPosts as $post) {
                $postsById[$post['post_id']] = $post;
            }
            foreach ($posts as &$post) {
                $replyTo = $post['reply_to'];
                $post['reference'] = $replyTo !== null && isset($postsById[$replyTo])
                    ? $postsById[$replyTo]
                    : null;
            }
            unset($post);
        }

        try {
            $visitorCount = $this->visitorCounter->count($request->getClientIp() ?? 'unknown');
        } catch (\RuntimeException) {
            $visitorCount = '参加者ファイル出力エラー';
        }

        try {
            $pageViewCount = $this->pageViewCounter->increment();
        } catch (\RuntimeException) {
            $pageViewCount = 'カウンターエラー';
        }

        return new Response($this->twig->render('bbs/index.html.twig', [
            'app_title' => $this->appTitle,
            'posts' => $posts,
            'range_start' => $startPosition,
            'range_end' => $startPosition + count($posts) - 1,
            'next_before' => $nextBefore,
            'display_count' => $pageSize,
            'auto_link_enabled' => $request->cookies->getString('bbs_auto_link', '1') !== '0',
            'visitor_count' => $visitorCount,
            'visitor_limit' => $this->visitorCounter->limit(),
            'page_view_count' => $pageViewCount,
            'page_view_started_at' => $this->pageViewStartedAt,
            'central_post_limit' => $this->centralPostLimit,
        ]));
    }

    #[Route('/archive', name: 'app_archive', methods: ['GET'])]
    public function archive(Request $request): Response
    {
        $archives = $this->archive->entries();
        $selectedArchives = $request->query->all('archives');
        $selectedArchives = array_values(array_filter($selectedArchives, 'is_string'));
        if (!$request->query->has('keyword') && $archives !== []) {
            $selectedArchives = [$archives[array_key_last($archives)]['date']];
        }
        $keyword = trim($request->query->getString('keyword'));

        return new Response($this->twig->render('bbs/archive.html.twig', [
            'app_title' => $this->appTitle,
            'archives' => $archives,
            'selected_archives' => $selectedArchives,
            'keyword' => $keyword,
            'search_performed' => $request->query->has('keyword'),
            'posts' => $this->archive->search($selectedArchives, $keyword),
        ]));
    }

    #[Route('/reply/{postId<\d+>}', name: 'app_reply', methods: ['GET'])]
    public function reply(Request $request, int $postId): Response
    {
        $reply = null;
        foreach ($this->posts->all() as $post) {
            if ($post['post_id'] === $postId) {
                $reply = $post;
                break;
            }
        }
        if ($reply === null) {
            throw new NotFoundHttpException('返信先が見つかりません。');
        }

        return new Response($this->twig->render('bbs/reply.html.twig', [
            'app_title' => $this->appTitle,
            'reply' => $reply,
            'reply_message' => $this->quotedReply($reply['message']),
            'display_count' => max(1, min(
                self::MAX_PAGE_SIZE,
                $request->cookies->getInt('bbs_display_count', self::DEFAULT_PAGE_SIZE),
            )),
            'auto_link_enabled' => $request->cookies->getString('bbs_auto_link', '1') !== '0',
        ]));
    }

    #[Route('/author', name: 'app_author_search', methods: ['GET'])]
    public function authorSearch(Request $request): Response
    {
        $author = trim($request->query->getString('name'), " \n\r\t\v\0　");
        if ($author === '') {
            throw new NotFoundHttpException('投稿者が指定されていません。');
        }

        $allPosts = $this->posts->all();
        $postsById = [];
        foreach ($allPosts as $post) {
            $postsById[$post['post_id']] = $post;
        }

        $posts = array_values(array_filter(
            $allPosts,
            static fn (array $post): bool => trim($post['author'], " \n\r\t\v\0　") === $author,
        ));
        foreach ($posts as &$post) {
            $replyTo = $post['reply_to'];
            $post['reference'] = $replyTo !== null && isset($postsById[$replyTo])
                ? $postsById[$replyTo]
                : null;
        }
        unset($post);

        return new Response($this->twig->render('bbs/author.html.twig', [
            'app_title' => $this->appTitle,
            'author' => $author,
            'posts' => $posts,
        ]));
    }

    #[Route('/thread/{threadId<\d+>}', name: 'app_thread', methods: ['GET'])]
    public function thread(int $threadId): Response
    {
        $thread = array_values(array_filter(
            $this->posts->all(),
            static fn (array $post): bool => $post['thread_id'] === $threadId,
        ));
        if ($thread === []) {
            throw new NotFoundHttpException('スレッドが見つかりません。');
        }

        $postsById = [];
        foreach ($thread as $post) {
            $postsById[$post['post_id']] = $post;
        }
        foreach ($thread as &$post) {
            $replyTo = $post['reply_to'];
            $post['reference'] = $replyTo !== null && isset($postsById[$replyTo])
                ? $postsById[$replyTo]
                : null;
        }
        unset($post);

        return new Response($this->twig->render('bbs/thread.html.twig', [
            'app_title' => $this->appTitle,
            'posts' => $thread,
        ]));
    }

    #[Route('/tree', name: 'app_tree', methods: ['GET'])]
    public function allTrees(): Response
    {
        return new Response($this->twig->render('bbs/tree_wip.html.twig', [
            'app_title' => $this->appTitle,
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

    private function quotedReply(string $message): string
    {
        $lines = preg_split('/\R/u', trim($message));
        if ($lines === false) {
            return '> ' . trim($message) . "\n\n";
        }

        return implode("\n", array_map(
            static fn (string $line): string => $line === '' ? '>' : '> ' . $line,
            $lines,
        )) . "\n\n";
    }
}
