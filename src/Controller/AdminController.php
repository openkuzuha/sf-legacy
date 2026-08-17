<?php

namespace App\Controller;

use App\Settings\AdminPassword;
use App\Settings\SiteSettings;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

final class AdminController
{
    private const string SESSION_KEY = 'admin_password_fingerprint';

    public function __construct(
        private readonly SiteSettings $siteSettings,
        private readonly AdminPassword $adminPassword,
        private readonly Environment $twig,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly RateLimiterFactoryInterface $adminLoginLimiter,
    ) {
    }

    #[Route('/admin/settings/password', name: 'app_admin_password', methods: ['POST'])]
    public function updatePassword(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }

        $token = new CsrfToken('admin_password', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToAdmin();
        }

        try {
            $this->adminPassword->change(
                $request->request->getString('current_password'),
                $request->request->getString('new_password'),
                $request->request->getString('new_password_confirmation'),
            );
            $request->getSession()->invalidate();

            return $this->redirectToAdmin();
        } catch (InvalidArgumentException | RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToAdmin();
    }

    #[Route('/admin', name: 'app_admin', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $session = $request->getSession();
        $session->start();
        $error = null;

        if ($request->isMethod('POST')) {
            $error = $this->authenticate($request, $session);
            if ($error === null) {
                return new Response('', Response::HTTP_SEE_OTHER, ['Location' => '/admin']);
            }
        }

        return new Response($this->twig->render('admin/index.html.twig', [
            'app_title' => $this->siteSettings->title(),
            'default_title' => $this->siteSettings->defaultTitle(),
            'authenticated' => $this->isAuthenticated($request),
            'error' => $error,
        ]));
    }

    #[Route('/admin/settings/title', name: 'app_admin_title', methods: ['POST'])]
    public function updateTitle(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }

        $token = new CsrfToken('admin_title', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToAdmin();
        }

        try {
            $this->siteSettings->setTitle($request->request->getString('title'));
            $this->addFlash($request->getSession(), 'admin_success', 'サイトタイトルを保存しました。');
        } catch (InvalidArgumentException | RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToAdmin();
    }

    #[Route('/admin/settings/title/reset', name: 'app_admin_title_reset', methods: ['POST'])]
    public function resetTitle(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }

        $token = new CsrfToken('admin_title_reset', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToAdmin();
        }

        try {
            $this->siteSettings->resetTitle();
            $this->addFlash($request->getSession(), 'admin_success', 'サイトタイトルを初期値に戻しました。');
        } catch (RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToAdmin();
    }

    #[Route('/admin/logout', name: 'app_admin_logout', methods: ['POST'])]
    public function logout(Request $request): Response
    {
        $token = new CsrfToken('admin_logout', $request->request->getString('_token'));
        if ($this->csrfTokenManager->isTokenValid($token)) {
            $request->getSession()->invalidate();
        }

        return new Response('', Response::HTTP_SEE_OTHER, ['Location' => '/admin']);
    }

    private function authenticate(Request $request, SessionInterface $session): ?string
    {
        $token = new CsrfToken('admin_login', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            return '入力の有効期限が切れました。もう一度お試しください。';
        }

        $limit = $this->adminLoginLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            return '試行回数が多すぎます。しばらく待ってからお試しください。';
        }

        if (!$this->adminPassword->isConfigured()) {
            return '管理用パスワードのハッシュが設定されていません。';
        }

        if (!$this->adminPassword->verify($request->request->getString('password'))) {
            return 'パスワードが正しくありません。';
        }

        $session->migrate(true);
        $session->set(self::SESSION_KEY, $this->adminPassword->fingerprint());

        return null;
    }

    private function isAuthenticated(Request $request): bool
    {
        $fingerprint = $request->getSession()->get(self::SESSION_KEY);

        return is_string($fingerprint) && hash_equals($this->adminPassword->fingerprint(), $fingerprint);
    }

    private function redirectToAdmin(): Response
    {
        return new Response('', Response::HTTP_SEE_OTHER, ['Location' => '/admin']);
    }

    private function addFlash(SessionInterface $session, string $type, string $message): void
    {
        $flashBag = $session->getBag('flashes');
        if (!$flashBag instanceof FlashBagInterface) {
            throw new RuntimeException('フラッシュメッセージを保存できません。');
        }
        $flashBag->add($type, $message);
    }
}
