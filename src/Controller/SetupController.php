<?php

namespace App\Controller;

use App\Settings\AdminPassword;
use App\Settings\EnvLocalWriter;
use App\Settings\SiteSettings;
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
        private readonly EnvLocalWriter $envLocalWriter,
        private readonly Environment $twig,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly RateLimiterFactoryInterface $adminSetupLimiter,
        #[Autowire(param: 'app.setup_token')]
        private readonly string $setupToken,
        #[Autowire(env: 'APP_SECRET')]
        private readonly string $appSecret,
        #[Autowire(env: 'AUDIT_HMAC_KEY')]
        private readonly string $auditHmacKey,
    ) {
    }

    #[Route('/admin/setup', name: 'app_admin_setup', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        if ($this->adminPassword->isConfigured()) {
            return $this->redirectToAdmin();
        }

        $token = $request->query->getString('token') ?: $request->request->getString('token');
        if ($this->setupToken !== '') {
            $limit = $this->adminSetupLimiter->create($request->getClientIp() ?? 'unknown')->consume();
            if (!$limit->isAccepted()) {
                throw new AccessDeniedHttpException('試行回数が多すぎます。しばらく待ってからお試しください。');
            }
            if (!hash_equals($this->setupToken, $token)) {
                throw new AccessDeniedHttpException('セットアップトークンが正しくありません。');
            }
        }

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
            'app_secret_configured' => $this->appSecret !== '',
            'audit_hmac_key_configured' => $this->auditHmacKey !== '',
        ]));
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
}
