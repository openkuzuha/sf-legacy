<?php

namespace App\Controller;

use App\Audit\AuditRecorder;
use App\Http\RequestIdSubscriber;
use App\Post\AuthorNamePolicy;
use App\Post\DeniedPostNetworkPolicy;
use App\Post\PostRepository;
use App\Settings\SiteSettings;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

final class SubmitController
{
    public function __construct(
        private readonly PostRepository $posts,
        private readonly AuthorNamePolicy $authorNamePolicy,
        private readonly DeniedPostNetworkPolicy $deniedPostNetworkPolicy,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RateLimiterFactoryInterface $postLimiter,
        private readonly RateLimiterFactoryInterface $postDuplicateLimiter,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly Environment $twig,
        private readonly SiteSettings $siteSettings,
        private readonly AuditRecorder $auditRecorder,
        #[Autowire(param: 'app.post_max_author_chars')]
        private readonly int $maxAuthorChars,
        #[Autowire(param: 'app.post_max_email_chars')]
        private readonly int $maxEmailChars,
        #[Autowire(param: 'app.post_max_title_chars')]
        private readonly int $maxTitleChars,
    ) {
    }

    #[Route('/submit', name: 'app_submit', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $token = new CsrfToken('post', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw new BadRequestHttpException('ページの有効期限が切れました。再読み込みしてもう一度お試しください。');
        }
        if (!$this->siteSettings->postingEnabled()) {
            throw new AccessDeniedHttpException('現在、投稿受付を停止しています。');
        }
        if ($this->deniedPostNetworkPolicy->denies($request->getClientIp())) {
            throw new AccessDeniedHttpException('このIPアドレスからは投稿できません。');
        }

        $displayCount = max(1, min(
            1000,
            $request->request->getInt('display_count', $this->siteSettings->defaultDisplayCount()),
        ));
        $autoLink = $request->request->getBoolean('auto_link');
        if ($request->request->getString('website') !== '') {
            return $this->redirectToBbs($request, $displayCount, $autoLink);
        }

        $message = $request->request->getString('content');
        if (trim($message) === '') {
            return $this->redirectToBbs($request, $displayCount, $autoLink);
        }

        $author = $request->request->getString('author');
        $email = $request->request->getString('email');
        $title = $request->request->getString('title');
        $this->validatePost($author, $email, $title, $message);

        $normalizedAuthor = $this->authorNamePolicy->forGeneralPost($author, $this->siteSettings->adminName());
        $author = $normalizedAuthor['author'];
        $authorSpoofed = $normalizedAuthor['spoofed'];

        $limit = $this->postLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());
            throw new TooManyRequestsHttpException($retryAfter, '投稿間隔を空けてもう一度お試しください。');
        }

        $duplicateKey = hash('sha256', ($request->getClientIp() ?? 'unknown') . '|' . $message);
        $duplicateLimit = $this->postDuplicateLimiter->create($duplicateKey)->consume();
        if (!$duplicateLimit->isAccepted()) {
            $retryAfter = max(1, $duplicateLimit->getRetryAfter()->getTimestamp() - time());
            throw new TooManyRequestsHttpException($retryAfter, '同じ内容の投稿は少し時間をおいてからもう一度お試しください。');
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

        $postId = $this->posts->append([
            'author' => $author,
            'author_spoofed' => $authorSpoofed,
            'email' => $email,
            'title' => $title,
            'message' => $message,
            'auto_link' => $autoLink,
            'thread_id' => $threadId,
            'reply_to' => $replyTo,
        ]);
        $requestId = $request->attributes->get(RequestIdSubscriber::ATTRIBUTE);
        $this->auditRecorder->record(
            is_string($requestId) ? $requestId : bin2hex(random_bytes(16)),
            'main',
            $postId,
            $request->getClientIp(),
            $request->headers->get('User-Agent'),
        );
        if ($this->siteSettings->undoEnabled()) {
            $request->getSession()->set('post_undo', [
                'post_id' => $postId,
                'token' => bin2hex(random_bytes(32)),
                'expires_at' => time() + $this->siteSettings->undoWindowSeconds(),
            ]);
        } else {
            $request->getSession()->remove('post_undo');
        }

        if ($replyTo !== null) {
            $returnTo = $request->request->getString('return_to');
            $isTree = preg_match('#^/tree(?:/\d+)?$#D', $returnTo) === 1;
            $response = new Response($this->twig->render('bbs/post_complete.html.twig', [
                'app_title' => $this->siteSettings->title(),
                'return_to' => $isTree ? $returnTo : $this->urlGenerator->generate('app_hello'),
            ]));

            return $this->addPreferenceCookies($response, $request, $displayCount, $autoLink);
        }

        return $this->redirectToBbs($request, $displayCount, $autoLink);
    }

    private function validatePost(string $author, string $email, string $title, string $message): void
    {
        if (!mb_check_encoding($author . $email . $title . $message, 'UTF-8')) {
            throw new BadRequestHttpException('投稿内容をUTF-8で入力してください。');
        }
        if (mb_strlen($author) > $this->maxAuthorChars) {
            throw new BadRequestHttpException(sprintf('投稿者名は%d文字以内で入力してください。', $this->maxAuthorChars));
        }
        if (mb_strlen($email) > $this->maxEmailChars) {
            throw new BadRequestHttpException(sprintf('メールアドレスは%d文字以内で入力してください。', $this->maxEmailChars));
        }
        if (mb_strlen($title) > $this->maxTitleChars) {
            throw new BadRequestHttpException(sprintf('題名は%d文字以内で入力してください。', $this->maxTitleChars));
        }
        $maxMessageChars = $this->siteSettings->maxMessageChars();
        if (mb_strlen($message) > $maxMessageChars) {
            throw new BadRequestHttpException(sprintf('本文は全体で%d文字以内で入力してください。', $maxMessageChars));
        }
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $message));
        $maxMessageLines = $this->siteSettings->maxMessageLines();
        if (count($lines) > $maxMessageLines) {
            throw new BadRequestHttpException(sprintf('本文は%d行以内で入力してください。', $maxMessageLines));
        }
        $maxLineChars = $this->siteSettings->maxLineChars();
        foreach ($lines as $line) {
            if (mb_strlen($line) > $maxLineChars) {
                throw new BadRequestHttpException(sprintf('本文の1行は%d文字以内で入力してください。', $maxLineChars));
            }
        }
        foreach ($this->siteSettings->prohibitedWords() as $word) {
            if (
                str_contains($author, $word)
                || str_contains($email, $word)
                || str_contains($title, $word)
                || str_contains($message, $word)
            ) {
                throw new BadRequestHttpException('投稿禁止ワードが含まれています。');
            }
        }
    }

    private function redirectToBbs(Request $request, int $displayCount, bool $autoLink): RedirectResponse
    {
        $returnTo = $request->request->getString('return_to');
        $isTree = preg_match('#^/tree(?:/\d+)?$#D', $returnTo) === 1;
        $parameters = !$isTree && $displayCount !== $this->siteSettings->defaultDisplayCount()
            ? ['display_count' => $displayCount]
            : [];
        $response = new RedirectResponse(
            $isTree ? $returnTo : $this->urlGenerator->generate('app_hello', $parameters),
            Response::HTTP_SEE_OTHER,
        );

        $this->addPreferenceCookies($response, $request, $displayCount, $autoLink);

        return $response;
    }

    private function addPreferenceCookies(
        Response $response,
        Request $request,
        int $displayCount,
        bool $autoLink,
    ): Response {
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
