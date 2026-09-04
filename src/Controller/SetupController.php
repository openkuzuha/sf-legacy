<?php

namespace App\Controller;

use App\Settings\AdminPassword;
use App\Settings\CloudBackendConnectivityChecker;
use App\Settings\CloudModeSetup;
use App\Settings\EnvLocalWriter;
use App\Settings\SiteSettings;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

final class SetupController
{
    public function __construct(
        private readonly SiteSettings $siteSettings,
        private readonly AdminPassword $adminPassword,
        private readonly CloudModeSetup $cloudModeSetup,
        private readonly CloudBackendConnectivityChecker $connectivityChecker,
        private readonly EnvLocalWriter $envLocalWriter,
        private readonly Environment $twig,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly RateLimiterFactoryInterface $adminSetupLimiter,
        private readonly RateLimiterFactoryInterface $adminSetupModeLimiter,
        #[Autowire(param: 'app.setup_token')]
        private readonly string $setupToken,
        #[Autowire(env: 'APP_SECRET')]
        private readonly string $appSecret,
        #[Autowire(env: 'AUDIT_HMAC_KEY')]
        private readonly string $auditHmacKey,
        private readonly string $appTimezone,
    ) {
    }

    #[Route('/admin/setup', name: 'app_admin_setup', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        if ($this->adminPassword->isConfigured()) {
            return $this->redirectToAdmin();
        }

        $token = $request->query->getString('token') ?: $request->request->getString('token');
        if (!$this->cloudModeSetup->isDecided()) {
            // 動作モードが未決定のまま/admin/setupへ直接来た場合も、
            // まず/admin/setup/modeへ回す。トークン検証はそちらで行う。
            return $this->redirectToSetupMode($token);
        }
        $this->assertSetupTokenValid($request, $this->adminSetupLimiter, $token);

        $error = null;
        $completed = null;
        if ($request->isMethod('POST')) {
            try {
                $completed = $this->complete($request);
            } catch (InvalidArgumentException | RuntimeException $exception) {
                $error = $exception->getMessage();
            }
        }

        return new Response($this->twig->render('admin/setup.html.twig', [
            'app_title' => $this->siteSettings->title(),
            'token' => $token,
            'error' => $error,
            'completed' => $completed,
            'title' => $this->siteSettings->title(),
            'admin_name' => $this->siteSettings->adminName(),
            'admin_email' => $this->siteSettings->adminEmail(),
            'service_started_at' => $this->serviceStartedAtFormValue($request),
            'app_secret_configured' => $this->appSecret !== '',
            'audit_hmac_key_configured' => $this->auditHmacKey !== '',
        ]));
    }

    /**
     * フォームへ表示するサービス開始日を決める。POST後の再表示では入力値を保ち、
     * それ以外（初回表示）では設定済みの固定既定値ではなく、実際の本日の日付を提示する。
     */
    private function serviceStartedAtFormValue(Request $request): string
    {
        if ($request->isMethod('POST')) {
            $submitted = $request->request->getString('service_started_at');
            if ($submitted !== '') {
                return $submitted;
            }
        }

        return (new DateTimeImmutable('today', new DateTimeZone($this->appTimezone)))->format('Y-m-d');
    }

    #[Route('/admin/setup/mode', name: 'app_admin_setup_mode', methods: ['GET', 'POST'])]
    public function mode(Request $request): Response
    {
        if ($this->adminPassword->isConfigured()) {
            return $this->redirectToAdmin();
        }

        $token = $request->query->getString('token') ?: $request->request->getString('token');
        if ($this->cloudModeSetup->isDecided()) {
            return $this->redirectToSetup($token);
        }
        $this->assertSetupTokenValid($request, $this->adminSetupModeLimiter, $token);

        $error = null;
        if ($request->isMethod('POST')) {
            $error = $this->decideMode($request);
            if ($error === null) {
                return $this->redirectToSetup($token);
            }
        }

        return new Response($this->twig->render('admin/setup_mode.html.twig', [
            'app_title' => $this->siteSettings->title(),
            'token' => $token,
            'error' => $error,
        ]));
    }

    private function assertSetupTokenValid(Request $request, RateLimiterFactoryInterface $limiter, string $token): void
    {
        if ($this->setupToken === '') {
            return;
        }
        $limit = $limiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            throw new AccessDeniedHttpException('試行回数が多すぎます。しばらく待ってからお試しください。');
        }
        if (!hash_equals($this->setupToken, $token)) {
            throw new AccessDeniedHttpException('セットアップトークンが正しくありません。');
        }
    }

    private function decideMode(Request $request): ?string
    {
        $csrfToken = new CsrfToken('admin_setup_mode', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($csrfToken)) {
            return '入力の有効期限が切れました。もう一度お試しください。';
        }

        $selected = $request->request->getString('cloud_mode');
        if ($selected !== '0' && $selected !== '1') {
            return 'ローカルモードとクラウドモードのどちらかを選択してください。';
        }

        $cloudMode = $selected === '1';
        if ($cloudMode) {
            $problems = $this->connectivityChecker->check();
            if ($problems !== []) {
                return implode("\n", [
                    'クラウドモードに必要な接続を確認できませんでした。設定を見直してから再度お試しください。',
                    ...$problems,
                ]);
            }
        }

        try {
            $this->cloudModeSetup->decide($cloudMode);
        } catch (RuntimeException $exception) {
            return $exception->getMessage();
        }

        return null;
    }

    /** @return array{app_secret: ?string, audit_hmac_key: ?string, env_write_failed: bool} */
    private function complete(Request $request): array
    {
        $csrfToken = new CsrfToken('admin_setup', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($csrfToken)) {
            throw new RuntimeException('入力の有効期限が切れました。もう一度お試しください。');
        }
        if ($this->adminPassword->isConfigured()) {
            throw new RuntimeException('管理用パスワードはすでに設定されています。');
        }

        $title = $request->request->getString('title');
        if ($title !== '') {
            $this->siteSettings->setTitle($title);
        }
        $adminName = $request->request->getString('admin_name');
        if ($adminName !== '') {
            $this->siteSettings->setAdminName($adminName);
        }
        $adminEmail = $request->request->getString('admin_email');
        if ($adminEmail !== '') {
            $this->siteSettings->setAdminEmail($adminEmail);
        }
        $serviceStartedAt = $request->request->getString('service_started_at');
        if ($serviceStartedAt !== '') {
            $this->siteSettings->setServiceStartedAt($serviceStartedAt);
        }

        $this->adminPassword->setInitial(
            $request->request->getString('password'),
            $request->request->getString('password_confirmation'),
        );

        $generatedAppSecret = null;
        $generatedAuditHmacKey = null;
        $envWriteFailed = false;
        $toWrite = [];
        if ($this->appSecret === '') {
            $generatedAppSecret = bin2hex(random_bytes(32));
            $toWrite['APP_SECRET'] = $generatedAppSecret;
        }
        if ($this->auditHmacKey === '') {
            $generatedAuditHmacKey = base64_encode(random_bytes(32));
            $toWrite['AUDIT_HMAC_KEY'] = $generatedAuditHmacKey;
        }
        if ($toWrite !== []) {
            try {
                $this->envLocalWriter->upsert($toWrite);
            } catch (RuntimeException) {
                $envWriteFailed = true;
            }
        }

        return [
            'app_secret' => $generatedAppSecret,
            'audit_hmac_key' => $generatedAuditHmacKey,
            'env_write_failed' => $envWriteFailed,
        ];
    }

    private function redirectToAdmin(): Response
    {
        return new Response('', Response::HTTP_SEE_OTHER, ['Location' => '/admin']);
    }

    private function redirectToSetup(string $token): Response
    {
        return new Response('', Response::HTTP_SEE_OTHER, [
            'Location' => '/admin/setup' . ($token !== '' ? '?token=' . urlencode($token) : ''),
        ]);
    }

    private function redirectToSetupMode(string $token): Response
    {
        return new Response('', Response::HTTP_SEE_OTHER, [
            'Location' => '/admin/setup/mode' . ($token !== '' ? '?token=' . urlencode($token) : ''),
        ]);
    }
}
