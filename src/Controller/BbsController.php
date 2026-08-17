<?php

namespace App\Controller;

use App\Content\LinkCollection;
use App\PageView\PageViewCounter;
use App\Post\PostArchive;
use App\Post\PostRepository;
use App\Settings\SiteSettings;
use App\Visitor\VisitorCounter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/** @phpstan-import-type PostRecord from \App\Post\PostTypes */
final class BbsController
{
    private const int MAX_PAGE_SIZE = 1000;

    public function __construct(
        private readonly SiteSettings $siteSettings,
        private readonly Environment $twig,
        private readonly PostRepository $posts,
        private readonly PostArchive $archive,
        private readonly VisitorCounter $visitorCounter,
        private readonly PageViewCounter $pageViewCounter,
        private readonly LinkCollection $links,
        #[Autowire(param: 'app.central_post_limit')]
        private readonly int $centralPostLimit,
    ) {
    }

    #[Route('/', name: 'app_hello')]
    public function __invoke(Request $request): Response
    {
        $storedPageSize = $request->cookies->getInt('bbs_display_count', $this->siteSettings->defaultDisplayCount());
        $pageSize = max(1, min(
            self::MAX_PAGE_SIZE,
            $request->query->has('display_count')
                ? $request->query->getInt('display_count')
                : $storedPageSize,
        ));
        $beforePostId = $request->query->getInt('before');
        $unreadRequested = $request->query->has('readnew');
        $unreadAfterPostId = max(0, $request->query->getInt('p'));
        $startPosition = 1;
        $allPosts = null;
        $candidates = [];
        $posts = [];
        $nextBefore = null;
        if ($unreadRequested) {
            $allPosts = $this->posts->all();
            $posts = array_values(array_filter(
                $allPosts,
                static fn (array $post): bool => $post['post_id'] > $unreadAfterPostId,
            ));
        } elseif ($beforePostId > 0) {
            $allPosts = $this->posts->all();
            $candidates = array_values(array_filter(
                $allPosts,
                static fn (array $post): bool => $post['post_id'] < $beforePostId,
            ));
            $startPosition = count($allPosts) - count($candidates) + 1;
        } else {
            $candidates = $this->posts->recent($pageSize + 1);
        }
        if (!$unreadRequested) {
            $hasMore = count($candidates) > $pageSize;
            $posts = array_slice($candidates, 0, $pageSize);
            $nextBefore = null;
            if ($hasMore && $posts !== []) {
                $nextBefore = $posts[count($posts) - 1]['post_id'];
            }
        }

        $allPosts ??= $this->posts->all();
        $latestPostId = $allPosts === [] ? 0 : max(array_column($allPosts, 'post_id'));

        $hasReplies = false;
        foreach ($posts as $post) {
            if ($post['reply_to'] !== null) {
                $hasReplies = true;
                break;
            }
        }
        if ($hasReplies) {
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
            'app_title' => $this->siteSettings->title(),
            'posts' => $posts,
            'range_start' => $startPosition,
            'range_end' => $startPosition + count($posts) - 1,
            'next_before' => $nextBefore,
            'latest_post_id' => $latestPostId,
            'unread_requested' => $unreadRequested,
            'display_count' => $pageSize,
            'auto_link_enabled' => $request->cookies->getString('bbs_auto_link', '1') !== '0',
            'visitor_count' => $visitorCount,
            'visitor_limit' => $this->visitorCounter->limit(),
            'page_view_count' => $pageViewCount,
            'page_view_started_at' => $this->siteSettings->formattedServiceStartedAt(),
            'central_post_limit' => $this->centralPostLimit,
            'max_message_lines' => $this->siteSettings->maxMessageLines(),
            'max_line_chars' => $this->siteSettings->maxLineChars(),
            'max_message_chars' => $this->siteSettings->maxMessageChars(),
            'additional_links' => $this->links->links(),
            'admin_email' => $this->siteSettings->adminEmail(),
            'admin_name' => $this->siteSettings->adminName(),
            'undo_enabled' => $this->siteSettings->undoEnabled(),
        ]));
    }

    #[Route('/archive', name: 'app_archive', methods: ['GET'])]
    public function archive(Request $request): Response
    {
        $archives = $this->archive->entries($this->siteSettings->archivePublicDays());
        $allowedDates = array_column($archives, 'date');
        $selectedArchives = $request->query->all('archives');
        foreach ($selectedArchives as $date) {
            if (!is_string($date) || !in_array($date, $allowedDates, true)) {
                throw new BadRequestHttpException('公開範囲外の過去ログは指定できません。');
            }
        }
        $selectedArchives = array_values($selectedArchives);
        if (!$request->query->has('keyword') && $archives !== []) {
            $selectedArchives = [$archives[array_key_last($archives)]['date']];
        }
        $keyword = trim($request->query->getString('keyword'));

        return new Response($this->twig->render('bbs/archive.html.twig', [
            'app_title' => $this->siteSettings->title(),
            'archives' => $archives,
            'selected_archives' => $selectedArchives,
            'keyword' => $keyword,
            'search_performed' => $request->query->has('keyword'),
            'posts' => $request->query->has('keyword')
                ? $this->archive->search($selectedArchives, $keyword)
                : [],
            'archive_public_days' => $this->siteSettings->archivePublicDays(),
        ]));
    }

    #[Route('/archive/topics', name: 'app_archive_topics', methods: ['GET'])]
    public function archiveTopics(Request $request): Response
    {
        $date = $request->query->getString('date');
        $posts = $this->archiveDate($date);

        $topics = [];
        foreach ($posts as $post) {
            $threadId = $post['thread_id'];
            if (!isset($topics[$threadId])) {
                $topics[$threadId] = [
                    'thread_id' => $threadId,
                    'reply_count' => 0,
                    'updated_at' => $post['posted_at'],
                    'digest' => $this->topicDigest($post['message']),
                ];
            } else {
                ++$topics[$threadId]['reply_count'];
                $topics[$threadId]['updated_at'] = $post['posted_at'];
            }
        }

        return new Response($this->twig->render('bbs/archive_topics.html.twig', [
            'app_title' => $this->siteSettings->title(),
            'date' => $date,
            'topics' => array_values($topics),
        ]));
    }

    #[Route('/archive/download', name: 'app_archive_download', methods: ['GET'])]
    public function archiveDownload(Request $request): Response
    {
        $date = $request->query->getString('date');
        $posts = $this->archiveDate($date);

        $html = $this->twig->render('bbs/archive_download.html.twig', [
            'app_title' => $this->siteSettings->title(),
            'date' => $date,
            'posts' => $posts,
        ]);

        $response = new Response($html);
        $response->headers->set('Content-Type', 'text/html; charset=UTF-8');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            str_replace('/', '', $date) . '.html',
        ));

        return $response;
    }

    #[Route('/personal-settings', name: 'app_personal_settings', methods: ['GET'])]
    public function personalSettings(): Response
    {
        return new Response($this->twig->render('bbs/personal_settings.html.twig', [
            'app_title' => $this->siteSettings->title(),
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
        $returnTo = $request->query->getString('return_to');
        if ($returnTo !== '/tree' && $returnTo !== sprintf('/tree/%d', $reply['thread_id'])) {
            $returnTo = null;
        }

        return new Response($this->twig->render('bbs/reply.html.twig', [
            'app_title' => $this->siteSettings->title(),
            'reply' => $reply,
            'reply_message' => $this->quotedReply($reply['message']),
            'return_to' => $returnTo,
            'display_count' => max(1, min(
                self::MAX_PAGE_SIZE,
                $request->cookies->getInt('bbs_display_count', $this->siteSettings->defaultDisplayCount()),
            )),
            'auto_link_enabled' => $request->cookies->getString('bbs_auto_link', '1') !== '0',
            'max_message_lines' => $this->siteSettings->maxMessageLines(),
            'max_line_chars' => $this->siteSettings->maxLineChars(),
            'max_message_chars' => $this->siteSettings->maxMessageChars(),
            'admin_name' => $this->siteSettings->adminName(),
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
            'app_title' => $this->siteSettings->title(),
            'author' => $author,
            'posts' => $posts,
            'undo_enabled' => $this->siteSettings->undoEnabled(),
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
            'app_title' => $this->siteSettings->title(),
            'posts' => $thread,
            'undo_enabled' => $this->siteSettings->undoEnabled(),
        ]));
    }

    #[Route('/tree', name: 'app_tree', methods: ['GET'])]
    public function allTrees(Request $request): Response
    {
        $allPosts = $this->posts->all();
        $latestPostId = $allPosts === [] ? 0 : max(array_column($allPosts, 'post_id'));
        $unreadRequested = $request->query->has('readnew');
        $unreadAfterPostId = max(0, $request->query->getInt('p'));
        $threads = [];
        foreach ($allPosts as $post) {
            $threads[$post['thread_id']][] = $post;
        }
        if ($unreadRequested) {
            $threads = array_filter(
                $threads,
                static function (array $thread) use ($unreadAfterPostId): bool {
                    foreach ($thread as $post) {
                        if ($post['post_id'] > $unreadAfterPostId) {
                            return true;
                        }
                    }

                    return false;
                },
            );
        }

        $trees = array_map(
            fn (array $thread): array => $this->buildTree($thread),
            array_values($threads),
        );
        usort(
            $trees,
            static fn (array $left, array $right): int => strcmp($right['updated_at'], $left['updated_at']),
        );

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

        return new Response($this->twig->render('bbs/all_trees.html.twig', [
            'app_title' => $this->siteSettings->title(),
            'trees' => $trees,
            'latest_post_id' => $latestPostId,
            'unread_requested' => $unreadRequested,
            'unread_after_post_id' => $unreadAfterPostId,
            'display_count' => max(1, min(
                self::MAX_PAGE_SIZE,
                $request->cookies->getInt('bbs_display_count', $this->siteSettings->defaultDisplayCount()),
            )),
            'auto_link_enabled' => $request->cookies->getString('bbs_auto_link', '1') !== '0',
            'visitor_count' => $visitorCount,
            'visitor_limit' => $this->visitorCounter->limit(),
            'page_view_count' => $pageViewCount,
            'page_view_started_at' => $this->siteSettings->formattedServiceStartedAt(),
            'central_post_limit' => $this->centralPostLimit,
            'max_message_lines' => $this->siteSettings->maxMessageLines(),
            'max_line_chars' => $this->siteSettings->maxLineChars(),
            'max_message_chars' => $this->siteSettings->maxMessageChars(),
            'admin_name' => $this->siteSettings->adminName(),
            'undo_enabled' => $this->siteSettings->undoEnabled(),
        ]));
    }

    #[Route('/tree/{threadId<\d+>}', name: 'app_thread_tree', methods: ['GET'])]
    public function tree(Request $request, int $threadId): Response
    {
        $thread = array_values(array_filter(
            $this->posts->all(),
            static fn (array $post): bool => $post['thread_id'] === $threadId,
        ));
        if ($thread === []) {
            throw new NotFoundHttpException('スレッドが見つかりません。');
        }

        $tree = $this->buildTree($thread);

        return new Response($this->twig->render('bbs/tree.html.twig', [
            'app_title' => $this->siteSettings->title(),
            'thread_id' => $tree['thread_id'],
            'updated_at' => $tree['updated_at'],
            'children' => $tree['children'],
            'unread_after_post_id' => max(0, $request->query->getInt('p')),
            'undo_enabled' => $this->siteSettings->undoEnabled(),
        ]));
    }

    /**
     * @param non-empty-list<PostRecord> $thread
     * @return array{thread_id: int, updated_at: string, children: array<array-key, list<PostRecord>>}
     */
    private function buildTree(array $thread): array
    {
        usort($thread, static fn (array $left, array $right): int => $left['post_id'] <=> $right['post_id']);
        $updatedAt = max(array_column($thread, 'posted_at'));
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

        return [
            'thread_id' => $thread[0]['thread_id'],
            'updated_at' => $updatedAt,
            'children' => $children,
        ];
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

    /** @return list<PostRecord> */
    private function archiveDate(string $date): array
    {
        $allowedDates = array_column(
            $this->archive->entries($this->siteSettings->archivePublicDays()),
            'date',
        );
        if (!in_array($date, $allowedDates, true)) {
            throw new BadRequestHttpException('公開範囲外の過去ログは指定できません。');
        }
        $posts = $this->archive->search([$date], '');
        if ($posts === []) {
            throw new NotFoundHttpException('過去ログが見つかりません。');
        }

        return $posts;
    }

    private function topicDigest(string $message): string
    {
        $stripped = $this->treeMessage($message);
        $joined = trim(preg_replace('/\R+/u', '', $stripped) ?? $stripped);

        return mb_strlen($joined) > 50 ? mb_substr($joined, 0, 50) . '…' : $joined;
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
