<?php

namespace App\Controller;

use App\Audit\AdminActionRecorder;
use App\Audit\AuditIdentity;
use App\Audit\AuditLog;
use App\Http\RequestIdSubscriber;
use App\Post\PostRepository;
use App\Post\PostArchive;
use App\Settings\AdminPassword;
use App\Settings\SiteSettings;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
    private const int POSTS_PAGE_SIZE = 50;
    private const array AUDIT_FIELD_LABELS = [
        'ip_address' => 'ホスト',
        'network_token' => 'ホスト（仮名化）',
        'user_agent' => 'UA',
        'client_token' => 'UA（仮名化）',
    ];

    public function __construct(
        private readonly SiteSettings $siteSettings,
        private readonly PostRepository $posts,
        private readonly PostArchive $archive,
        private readonly AdminPassword $adminPassword,
        private readonly AuditIdentity $auditIdentity,
        private readonly AuditLog $auditLog,
        private readonly AdminActionRecorder $adminActionRecorder,
        private readonly Environment $twig,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly RateLimiterFactoryInterface $adminLoginLimiter,
        #[Autowire(param: 'kernel.environment')]
        private readonly string $appEnvironment,
        #[Autowire(param: 'app.cloud_mode')]
        private readonly bool $cloudMode,
        #[Autowire(param: 'app.post_max_title_chars')]
        private readonly int $maxTitleChars,
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

            return $this->redirectToSettings();
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

        return $this->redirectToSettings();
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
                return $this->redirectToSettings();
            }
        }

        if ($this->isAuthenticated($request)) {
            return $this->redirectToSettings();
        }

        return new Response($this->twig->render('admin/index.html.twig', [
            'app_title' => $this->siteSettings->title(),
            'error' => $error,
        ]));
    }

    #[Route('/admin/settings', name: 'app_admin_settings', methods: ['GET'])]
    public function settings(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }

        return new Response($this->twig->render('admin/settings.html.twig', [
            'app_title' => $this->siteSettings->title(),
            'default_title' => $this->siteSettings->defaultTitle(),
            'central_post_limit' => $this->siteSettings->centralPostLimit(),
            'default_central_post_limit' => $this->siteSettings->defaultCentralPostLimit(),
            'default_display_count' => $this->siteSettings->defaultDisplayCount(),
            'configured_default_display_count' => $this->siteSettings->configuredDefaultDisplayCount(),
            'max_message_lines' => $this->siteSettings->maxMessageLines(),
            'default_max_message_lines' => $this->siteSettings->defaultMaxMessageLines(),
            'max_line_chars' => $this->siteSettings->maxLineChars(),
            'default_max_line_chars' => $this->siteSettings->defaultMaxLineChars(),
            'max_message_chars' => $this->siteSettings->maxMessageChars(),
            'default_max_message_chars' => $this->siteSettings->defaultMaxMessageChars(),
            'visitor_active_seconds' => $this->siteSettings->visitorActiveSeconds(),
            'default_visitor_active_seconds' => $this->siteSettings->defaultVisitorActiveSeconds(),
            'service_started_at' => $this->siteSettings->serviceStartedAt(),
            'default_service_started_at' => $this->siteSettings->defaultServiceStartedAt(),
            'admin_name' => $this->siteSettings->adminName(),
            'default_admin_name' => $this->siteSettings->defaultAdminName(),
            'admin_email' => $this->siteSettings->adminEmail(),
            'prohibited_words' => $this->siteSettings->prohibitedWordsText(),
            'denied_post_networks' => $this->siteSettings->deniedPostNetworksText(),
            'denied_access_networks' => $this->siteSettings->deniedAccessNetworksText(),
            'posting_enabled' => $this->siteSettings->postingEnabled(),
            'maintenance_enabled' => $this->siteSettings->maintenanceEnabled(),
            'maintenance_message' => $this->siteSettings->maintenanceMessage(),
            'maintenance_ends_at' => $this->siteSettings->maintenanceEndsAt()?->format('Y-m-d\TH:i') ?? '',
            'undo_enabled' => $this->siteSettings->undoEnabled(),
            'default_undo_enabled' => $this->siteSettings->defaultUndoEnabled(),
            'undo_window_seconds' => $this->siteSettings->undoWindowSeconds(),
            'default_undo_window_seconds' => $this->siteSettings->defaultUndoWindowSeconds(),
            'archive_retention_days' => $this->siteSettings->archiveRetentionDays(),
            'default_archive_retention_days' => $this->siteSettings->defaultArchiveRetentionDays(),
            'archive_public_days' => $this->siteSettings->archivePublicDays(),
            'host_audit_mode' => $this->siteSettings->hostAuditMode(),
            'ua_audit_mode' => $this->siteSettings->uaAuditMode(),
            'audit_retention_days' => $this->siteSettings->auditRetentionDays(),
            'audit_key_configured' => $this->auditIdentity->isConfigured(),
            'default_archive_public_days' => $this->siteSettings->defaultArchivePublicDays(),
            'app_environment' => $this->appEnvironment,
            'cloud_mode' => $this->cloudMode,
        ]));
    }

    #[Route('/admin/settings/audit', name: 'app_admin_audit', methods: ['POST'])]
    public function updateAuditSettings(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }
        $token = new CsrfToken('admin_audit', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToSettings();
        }
        try {
            $days = $request->request->getString('audit_retention_days');
            if (filter_var($days, FILTER_VALIDATE_INT) === false) {
                throw new InvalidArgumentException('投稿者監査情報の保存日数を整数で入力してください。');
            }
            $hostMode = $request->request->getString('host_audit_mode');
            $uaMode = $request->request->getString('ua_audit_mode');
            if (
                ($hostMode === 'pseudonymous' || $uaMode === 'pseudonymous')
                && !$this->auditIdentity->isConfigured()
            ) {
                throw new InvalidArgumentException('AUDIT_HMAC_KEYを設定してから仮名化記録を有効にしてください。');
            }
            $this->siteSettings->setAuditSettings($hostMode, $uaMode, (int) $days);
            $this->addFlash($request->getSession(), 'admin_success', '投稿者監査設定を保存しました。');
        } catch (InvalidArgumentException | RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToSettings();
    }

    #[Route('/admin/settings/audit/reset', name: 'app_admin_audit_reset', methods: ['POST'])]
    public function resetAuditSettings(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }
        $token = new CsrfToken('admin_audit_reset', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToSettings();
        }
        try {
            $this->siteSettings->resetAuditSettings();
            $this->addFlash($request->getSession(), 'admin_success', '投稿者監査設定を初期値に戻しました。');
        } catch (RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToSettings();
    }

    #[Route('/admin/settings/operation-status', name: 'app_admin_operation_status', methods: ['POST'])]
    public function updateOperationStatus(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }
        $token = new CsrfToken('admin_operation_status', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToSettings();
        }
        try {
            $this->siteSettings->setOperationStatus(
                $request->request->getBoolean('posting_enabled'),
                $request->request->getBoolean('maintenance_enabled'),
                $request->request->getString('maintenance_message'),
                $request->request->getString('maintenance_ends_at'),
            );
            $this->addFlash($request->getSession(), 'admin_success', '運用状態を保存しました。');
        } catch (InvalidArgumentException | RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToSettings();
    }

    #[Route('/admin/settings/operation-status/reset', name: 'app_admin_operation_status_reset', methods: ['POST'])]
    public function resetOperationStatus(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }
        $token = new CsrfToken('admin_operation_status_reset', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToSettings();
        }
        try {
            $this->siteSettings->resetOperationStatus();
            $this->addFlash($request->getSession(), 'admin_success', '運用状態を初期値に戻しました。');
        } catch (RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToSettings();
    }

    #[Route('/admin/settings/archive-public-days', name: 'app_admin_archive_public_days', methods: ['POST'])]
    public function updateArchivePublicDays(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }
        $token = new CsrfToken('admin_archive_public_days', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToSettings();
        }
        try {
            $rawDays = $request->request->getString('archive_public_days');
            if (filter_var($rawDays, FILTER_VALIDATE_INT) === false) {
                throw new InvalidArgumentException('過去ログ公開日数を整数で入力してください。');
            }
            $this->siteSettings->setArchivePublicDays((int) $rawDays);
            $this->addFlash($request->getSession(), 'admin_success', '過去ログ公開日数を保存しました。');
        } catch (InvalidArgumentException | RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToSettings();
    }

    #[Route(
        '/admin/settings/archive-public-days/reset',
        name: 'app_admin_archive_public_days_reset',
        methods: ['POST'],
    )]
    public function resetArchivePublicDays(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }
        $token = new CsrfToken('admin_archive_public_days_reset', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToSettings();
        }
        try {
            $this->siteSettings->resetArchivePublicDays();
            $this->addFlash($request->getSession(), 'admin_success', '過去ログ公開日数を初期値に戻しました。');
        } catch (RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToSettings();
    }

    #[Route('/admin/settings/archive-retention', name: 'app_admin_archive_retention', methods: ['POST'])]
    public function updateArchiveRetention(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }
        $token = new CsrfToken('admin_archive_retention', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToSettings();
        }
        try {
            $rawDays = $request->request->getString('archive_retention_days');
            if (filter_var($rawDays, FILTER_VALIDATE_INT) === false) {
                throw new InvalidArgumentException('過去ログ保持日数を整数で入力してください。');
            }
            $days = (int) $rawDays;
            $this->siteSettings->setArchiveRetentionDays($days);
            $deleted = $this->archive->prune($days);
            $message = $days === 0
                ? '過去ログ保持日数を無期限で保存しました。'
                : sprintf('過去ログ保持日数を保存し、期限切れの投稿を%d件削除しました。', $deleted);
            $this->addFlash($request->getSession(), 'admin_success', $message);
        } catch (InvalidArgumentException | RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToSettings();
    }

    #[Route('/admin/settings/archive-retention/reset', name: 'app_admin_archive_retention_reset', methods: ['POST'])]
    public function resetArchiveRetention(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }
        $token = new CsrfToken('admin_archive_retention_reset', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToSettings();
        }
        try {
            $this->siteSettings->resetArchiveRetentionDays();
            $this->addFlash($request->getSession(), 'admin_success', '過去ログ保持日数を初期値に戻しました。');
        } catch (RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToSettings();
    }

    #[Route('/admin/settings/undo', name: 'app_admin_undo', methods: ['POST'])]
    public function updateUndoSettings(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }
        $token = new CsrfToken('admin_undo', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToSettings();
        }
        try {
            $rawSeconds = $request->request->getString('undo_window_seconds');
            if (filter_var($rawSeconds, FILTER_VALIDATE_INT) === false) {
                throw new InvalidArgumentException('投稿の削除可能時間を整数で入力してください。');
            }
            $this->siteSettings->setUndoSettings(
                $request->request->getBoolean('undo_enabled'),
                (int) $rawSeconds,
            );
            $this->addFlash($request->getSession(), 'admin_success', '投稿者による削除設定を保存しました。');
        } catch (InvalidArgumentException | RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToSettings();
    }

    #[Route('/admin/settings/undo/reset', name: 'app_admin_undo_reset', methods: ['POST'])]
    public function resetUndoSettings(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }
        $token = new CsrfToken('admin_undo_reset', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToSettings();
        }
        try {
            $this->siteSettings->resetUndoSettings();
            $this->addFlash($request->getSession(), 'admin_success', '投稿者による削除設定を初期値に戻しました。');
        } catch (RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToSettings();
    }

    #[Route('/admin/settings/prohibited-words', name: 'app_admin_prohibited_words', methods: ['POST'])]
    public function updateProhibitedWords(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }
        $token = new CsrfToken('admin_prohibited_words', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToSettings();
        }
        try {
            $this->siteSettings->setProhibitedWordsText($request->request->getString('prohibited_words'));
            $this->addFlash($request->getSession(), 'admin_success', '投稿禁止ワードを保存しました。');
        } catch (InvalidArgumentException | RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToSettings();
    }

    #[Route('/admin/settings/denied-post-networks', name: 'app_admin_denied_post_networks', methods: ['POST'])]
    public function updateDeniedPostNetworks(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }
        $token = new CsrfToken('admin_denied_post_networks', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToSettings();
        }
        try {
            $this->siteSettings->setDeniedPostNetworksText($request->request->getString('denied_post_networks'));
            $this->addFlash($request->getSession(), 'admin_success', '投稿禁止IPアドレス・CIDRを保存しました。');
        } catch (InvalidArgumentException | RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToSettings();
    }

    #[Route(
        '/admin/settings/denied-post-networks/reset',
        name: 'app_admin_denied_post_networks_reset',
        methods: ['POST'],
    )]
    public function resetDeniedPostNetworks(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }
        $token = new CsrfToken('admin_denied_post_networks_reset', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToSettings();
        }
        try {
            $this->siteSettings->resetDeniedPostNetworks();
            $this->addFlash($request->getSession(), 'admin_success', '投稿禁止IPアドレス・CIDRを初期値に戻しました。');
        } catch (RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToSettings();
    }

    #[Route('/admin/settings/denied-access-networks', name: 'app_admin_denied_access_networks', methods: ['POST'])]
    public function updateDeniedAccessNetworks(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }
        $token = new CsrfToken('admin_denied_access_networks', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToSettings();
        }
        try {
            $this->siteSettings->setDeniedAccessNetworksText($request->request->getString('denied_access_networks'));
            $this->addFlash($request->getSession(), 'admin_success', 'アクセス禁止IPアドレス・CIDRを保存しました。');
        } catch (InvalidArgumentException | RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToSettings();
    }

    #[Route(
        '/admin/settings/denied-access-networks/reset',
        name: 'app_admin_denied_access_networks_reset',
        methods: ['POST'],
    )]
    public function resetDeniedAccessNetworks(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }
        $token = new CsrfToken('admin_denied_access_networks_reset', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToSettings();
        }
        try {
            $this->siteSettings->resetDeniedAccessNetworks();
            $this->addFlash($request->getSession(), 'admin_success', 'アクセス禁止IPアドレス・CIDRを初期値に戻しました。');
        } catch (RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToSettings();
    }

    #[Route('/admin/settings/prohibited-words/reset', name: 'app_admin_prohibited_words_reset', methods: ['POST'])]
    public function resetProhibitedWords(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }
        $token = new CsrfToken('admin_prohibited_words_reset', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToSettings();
        }
        try {
            $this->siteSettings->resetProhibitedWords();
            $this->addFlash($request->getSession(), 'admin_success', '投稿禁止ワードを初期値に戻しました。');
        } catch (RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToSettings();
    }

    #[Route('/admin/settings/default-display-count', name: 'app_admin_default_display_count', methods: ['POST'])]
    public function updateDefaultDisplayCount(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }
        $token = new CsrfToken('admin_default_display_count', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToSettings();
        }
        try {
            $rawCount = $request->request->getString('default_display_count');
            if (filter_var($rawCount, FILTER_VALIDATE_INT) === false) {
                throw new InvalidArgumentException('初期表示件数を整数で入力してください。');
            }
            $this->siteSettings->setDefaultDisplayCount((int) $rawCount);
            $this->addFlash($request->getSession(), 'admin_success', '初期表示件数を保存しました。');
        } catch (InvalidArgumentException | RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToSettings();
    }

    #[Route(
        '/admin/settings/default-display-count/reset',
        name: 'app_admin_default_display_count_reset',
        methods: ['POST'],
    )]
    public function resetDefaultDisplayCount(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }
        $token = new CsrfToken('admin_default_display_count_reset', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToSettings();
        }
        try {
            $this->siteSettings->resetDefaultDisplayCount();
            $this->addFlash($request->getSession(), 'admin_success', '初期表示件数を初期値に戻しました。');
        } catch (RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToSettings();
    }

    #[Route('/admin/settings/message-limits', name: 'app_admin_message_limits', methods: ['POST'])]
    public function updateMessageLimits(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }
        $token = new CsrfToken('admin_message_limits', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToSettings();
        }
        try {
            $rawLines = $request->request->getString('max_message_lines');
            $rawChars = $request->request->getString('max_line_chars');
            $rawMessageChars = $request->request->getString('max_message_chars');
            if (filter_var($rawLines, FILTER_VALIDATE_INT) === false) {
                throw new InvalidArgumentException('本文の最大行数を整数で入力してください。');
            }
            if (filter_var($rawChars, FILTER_VALIDATE_INT) === false) {
                throw new InvalidArgumentException('1行の最大文字数を整数で入力してください。');
            }
            if (filter_var($rawMessageChars, FILTER_VALIDATE_INT) === false) {
                throw new InvalidArgumentException('本文全体の最大文字数を整数で入力してください。');
            }
            $lines = (int) $rawLines;
            $chars = (int) $rawChars;
            $messageChars = (int) $rawMessageChars;
            if ($lines < SiteSettings::MIN_MESSAGE_LIMIT || $lines > SiteSettings::MAX_MESSAGE_LIMIT) {
                throw new InvalidArgumentException('本文の最大行数は1以上10000以下で入力してください。');
            }
            if ($chars < SiteSettings::MIN_MESSAGE_LIMIT || $chars > SiteSettings::MAX_MESSAGE_LIMIT) {
                throw new InvalidArgumentException('1行の最大文字数は1以上10000以下で入力してください。');
            }
            if ($messageChars < SiteSettings::MIN_MESSAGE_LIMIT || $messageChars > SiteSettings::MAX_MESSAGE_LIMIT) {
                throw new InvalidArgumentException('本文全体の最大文字数は1以上10000以下で入力してください。');
            }
            $this->siteSettings->setMaxMessageLines($lines);
            $this->siteSettings->setMaxLineChars($chars);
            $this->siteSettings->setMaxMessageChars($messageChars);
            $this->addFlash($request->getSession(), 'admin_success', '本文入力制限を保存しました。');
        } catch (InvalidArgumentException | RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToSettings();
    }

    #[Route('/admin/settings/message-limits/reset', name: 'app_admin_message_limits_reset', methods: ['POST'])]
    public function resetMessageLimits(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }
        $token = new CsrfToken('admin_message_limits_reset', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToSettings();
        }
        try {
            $this->siteSettings->resetMaxMessageLines();
            $this->siteSettings->resetMaxLineChars();
            $this->siteSettings->resetMaxMessageChars();
            $this->addFlash($request->getSession(), 'admin_success', '本文入力制限を初期値に戻しました。');
        } catch (RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToSettings();
    }

    #[Route('/admin/settings/visitor-active-seconds', name: 'app_admin_visitor_active_seconds', methods: ['POST'])]
    public function updateVisitorActiveSeconds(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }
        $token = new CsrfToken('admin_visitor_active_seconds', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToSettings();
        }
        try {
            $rawSeconds = $request->request->getString('visitor_active_seconds');
            if (filter_var($rawSeconds, FILTER_VALIDATE_INT) === false) {
                throw new InvalidArgumentException('参加者カウント有効時間を整数で入力してください。');
            }
            $this->siteSettings->setVisitorActiveSeconds((int) $rawSeconds);
            $this->addFlash($request->getSession(), 'admin_success', '参加者カウント有効時間を保存しました。');
        } catch (InvalidArgumentException | RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToSettings();
    }

    #[Route(
        '/admin/settings/visitor-active-seconds/reset',
        name: 'app_admin_visitor_active_seconds_reset',
        methods: ['POST'],
    )]
    public function resetVisitorActiveSeconds(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }
        $token = new CsrfToken('admin_visitor_active_seconds_reset', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToSettings();
        }
        try {
            $this->siteSettings->resetVisitorActiveSeconds();
            $this->addFlash($request->getSession(), 'admin_success', '参加者カウント有効時間を初期値に戻しました。');
        } catch (RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToSettings();
    }

    #[Route('/admin/settings/service-started-at', name: 'app_admin_service_started_at', methods: ['POST'])]
    public function updateServiceStartedAt(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }
        $token = new CsrfToken('admin_service_started_at', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToSettings();
        }
        try {
            $this->siteSettings->setServiceStartedAt($request->request->getString('service_started_at'));
            $this->addFlash($request->getSession(), 'admin_success', 'サービス開始日を保存しました。');
        } catch (InvalidArgumentException | RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToSettings();
    }

    #[Route('/admin/settings/service-started-at/reset', name: 'app_admin_service_started_at_reset', methods: ['POST'])]
    public function resetServiceStartedAt(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }
        $token = new CsrfToken('admin_service_started_at_reset', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToSettings();
        }
        try {
            $this->siteSettings->resetServiceStartedAt();
            $this->addFlash($request->getSession(), 'admin_success', 'サービス開始日を初期値に戻しました。');
        } catch (RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToSettings();
    }

    #[Route('/admin/settings/admin-identity', name: 'app_admin_identity', methods: ['POST'])]
    public function updateAdminIdentity(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }
        $token = new CsrfToken('admin_identity', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToSettings();
        }
        try {
            $this->siteSettings->setAdminIdentity(
                $request->request->getString('admin_name'),
                $request->request->getString('admin_email'),
            );
            $this->addFlash($request->getSession(), 'admin_success', '管理者情報を保存しました。');
        } catch (InvalidArgumentException | RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToSettings();
    }

    #[Route('/admin/settings/admin-identity/reset', name: 'app_admin_identity_reset', methods: ['POST'])]
    public function resetAdminIdentity(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }
        $token = new CsrfToken('admin_identity_reset', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToSettings();
        }
        try {
            $this->siteSettings->resetAdminName();
            $this->siteSettings->resetAdminEmail();
            $this->addFlash($request->getSession(), 'admin_success', '管理者情報を初期値に戻しました。');
        } catch (RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToSettings();
    }

    #[Route('/admin/settings/central-post-limit', name: 'app_admin_central_post_limit', methods: ['POST'])]
    public function updateCentralPostLimit(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }
        $token = new CsrfToken('admin_central_post_limit', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToSettings();
        }
        try {
            $rawLimit = $request->request->getString('central_post_limit');
            if (filter_var($rawLimit, FILTER_VALIDATE_INT) === false) {
                throw new InvalidArgumentException('マスターログ保存件数を整数で入力してください。');
            }
            $limit = (int) $rawLimit;
            $this->siteSettings->setCentralPostLimit($limit);
            $this->posts->trimTo($limit);
            $this->addFlash($request->getSession(), 'admin_success', 'マスターログ保存件数を保存し、現在のログへ反映しました。');
        } catch (InvalidArgumentException | RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToSettings();
    }

    #[Route('/admin/settings/central-post-limit/reset', name: 'app_admin_central_post_limit_reset', methods: ['POST'])]
    public function resetCentralPostLimit(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }
        $token = new CsrfToken('admin_central_post_limit_reset', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToSettings();
        }
        try {
            $this->siteSettings->resetCentralPostLimit();
            $this->posts->trimTo($this->siteSettings->defaultCentralPostLimit());
            $this->addFlash($request->getSession(), 'admin_success', 'マスターログ保存件数を初期値に戻しました。');
        } catch (RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToSettings();
    }

    #[Route('/admin/posts', name: 'app_admin_posts', methods: ['GET'])]
    public function posts(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }

        $beforePostId = $request->query->getInt('before');
        $auditField = $request->query->getString('audit_field');
        $auditValue = $request->query->getString('audit_value');
        $auditFilter = $auditField !== '' && $auditValue !== '' && isset(self::AUDIT_FIELD_LABELS[$auditField])
            ? ['field' => $auditField, 'value' => $auditValue, 'label' => self::AUDIT_FIELD_LABELS[$auditField]]
            : null;

        $allPosts = null;
        if ($beforePostId > 0 || $auditFilter !== null) {
            $matchedPostIds = $auditFilter !== null
                ? array_flip($this->auditLog->findPostIds('main', $auditFilter['field'], $auditFilter['value']))
                : null;
            $allPosts = $this->posts->all();
            $candidates = array_values(array_filter(
                $allPosts,
                static function (array $post) use ($beforePostId, $matchedPostIds): bool {
                    if ($matchedPostIds !== null && !isset($matchedPostIds[$post['post_id']])) {
                        return false;
                    }

                    return $beforePostId <= 0 || $post['post_id'] < $beforePostId;
                },
            ));
        } else {
            $candidates = $this->posts->recent(self::POSTS_PAGE_SIZE + 1);
        }
        $hasMore = count($candidates) > self::POSTS_PAGE_SIZE;
        $posts = array_slice($candidates, 0, self::POSTS_PAGE_SIZE);
        $nextBefore = $hasMore && $posts !== [] ? $posts[count($posts) - 1]['post_id'] : null;

        $replyToId = $request->query->getInt('reply_to') ?: null;
        $replyTo = null;
        if ($replyToId !== null) {
            $allPosts ??= $this->posts->all();
            foreach ($allPosts as $post) {
                if ($post['post_id'] === $replyToId) {
                    $replyTo = $post;
                    break;
                }
            }
        }

        $auditRecords = $this->auditLog->findByPosts('main', array_map(
            static fn (array $post): array => ['post_id' => $post['post_id'], 'posted_at' => $post['posted_at']],
            $posts,
        ));

        return new Response($this->twig->render('admin/posts.html.twig', [
            'app_title' => $this->siteSettings->title(),
            'admin_name' => $this->siteSettings->adminName(),
            'posts' => $posts,
            'audit_records' => $auditRecords,
            'audit_field_labels' => self::AUDIT_FIELD_LABELS,
            'audit_filter' => $auditFilter,
            'host_audit_mode' => $this->siteSettings->hostAuditMode(),
            'ua_audit_mode' => $this->siteSettings->uaAuditMode(),
            'audit_key_configured' => $this->auditIdentity->isConfigured(),
            'before' => $beforePostId > 0 ? $beforePostId : null,
            'next_before' => $nextBefore,
            'reply_to' => $replyTo,
            'max_message_lines' => $this->siteSettings->maxMessageLines(),
            'max_line_chars' => $this->siteSettings->maxLineChars(),
            'max_message_chars' => $this->siteSettings->maxMessageChars(),
            'max_title_chars' => $this->maxTitleChars,
        ]));
    }

    #[Route('/admin/posts/new', name: 'app_admin_post_create', methods: ['POST'])]
    public function createPost(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }
        $token = new CsrfToken('admin_post_create', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToPosts($request);
        }

        $title = $request->request->getString('title');
        $message = $request->request->getString('message');
        $replyTo = $request->request->getInt('reply_to') ?: null;

        try {
            $this->validateAdminPost($title, $message);

            $threadId = null;
            if ($replyTo !== null) {
                $parent = null;
                foreach ($this->posts->all() as $post) {
                    if ($post['post_id'] === $replyTo) {
                        $parent = $post;
                        break;
                    }
                }
                if ($parent === null) {
                    throw new InvalidArgumentException('返信先の投稿が見つかりませんでした。');
                }
                $threadId = $parent['thread_id'];
            }

            $postId = $this->posts->append([
                'author' => $this->siteSettings->adminName(),
                'author_spoofed' => false,
                'email' => '',
                'title' => $title,
                'message' => $message,
                'auto_link' => true,
                'thread_id' => $threadId,
                'reply_to' => $replyTo,
            ]);
            $this->adminActionRecorder->record(
                $this->requestId($request),
                'post_create',
                $postId,
                $threadId ?? $postId,
            );
            $this->addFlash($request->getSession(), 'admin_success', '投稿しました。');

            return new Response('', Response::HTTP_SEE_OTHER, ['Location' => '/admin/posts']);
        } catch (InvalidArgumentException | RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToPosts($request);
    }

    #[Route('/admin/posts/{postId<\d+>}/delete', name: 'app_admin_post_delete', methods: ['POST'])]
    public function deletePost(Request $request, int $postId): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->redirectToAdmin();
        }
        $token = new CsrfToken('admin_post_delete', $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash($request->getSession(), 'admin_error', '入力の有効期限が切れました。');

            return $this->redirectToPosts($request);
        }

        if ($this->posts->delete($postId)) {
            $this->adminActionRecorder->record($this->requestId($request), 'post_delete', $postId, null);
            $this->addFlash($request->getSession(), 'admin_success', '投稿を削除しました。');
        } else {
            $this->addFlash($request->getSession(), 'admin_error', '該当の投稿が見つかりませんでした。');
        }

        return $this->redirectToPosts($request);
    }

    private function validateAdminPost(string $title, string $message): void
    {
        if (!mb_check_encoding($title . $message, 'UTF-8')) {
            throw new InvalidArgumentException('投稿内容をUTF-8で入力してください。');
        }
        if (trim($message) === '') {
            throw new InvalidArgumentException('本文を入力してください。');
        }
        if (mb_strlen($title) > $this->maxTitleChars) {
            throw new InvalidArgumentException(sprintf('題名は%d文字以内で入力してください。', $this->maxTitleChars));
        }
        $maxMessageChars = $this->siteSettings->maxMessageChars();
        if (mb_strlen($message) > $maxMessageChars) {
            throw new InvalidArgumentException(sprintf('本文は全体で%d文字以内で入力してください。', $maxMessageChars));
        }
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $message));
        $maxMessageLines = $this->siteSettings->maxMessageLines();
        if (count($lines) > $maxMessageLines) {
            throw new InvalidArgumentException(sprintf('本文は%d行以内で入力してください。', $maxMessageLines));
        }
        $maxLineChars = $this->siteSettings->maxLineChars();
        foreach ($lines as $line) {
            if (mb_strlen($line) > $maxLineChars) {
                throw new InvalidArgumentException(sprintf('本文の1行は%d文字以内で入力してください。', $maxLineChars));
            }
        }
    }

    private function requestId(Request $request): string
    {
        $requestId = $request->attributes->get(RequestIdSubscriber::ATTRIBUTE);

        return is_string($requestId) ? $requestId : bin2hex(random_bytes(16));
    }

    private function redirectToPosts(Request $request): Response
    {
        $params = [];
        $before = $request->request->getInt('before');
        if ($before > 0) {
            $params['before'] = $before;
        }
        $auditField = $request->request->getString('audit_field');
        $auditValue = $request->request->getString('audit_value');
        if ($auditField !== '' && $auditValue !== '' && isset(self::AUDIT_FIELD_LABELS[$auditField])) {
            $params['audit_field'] = $auditField;
            $params['audit_value'] = $auditValue;
        }

        return new Response('', Response::HTTP_SEE_OTHER, [
            'Location' => $params === [] ? '/admin/posts' : '/admin/posts?' . http_build_query($params),
        ]);
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

            return $this->redirectToSettings();
        }

        try {
            $this->siteSettings->setTitle($request->request->getString('title'));
            $this->addFlash($request->getSession(), 'admin_success', 'サイトタイトルを保存しました。');
        } catch (InvalidArgumentException | RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToSettings();
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

            return $this->redirectToSettings();
        }

        try {
            $this->siteSettings->resetTitle();
            $this->addFlash($request->getSession(), 'admin_success', 'サイトタイトルを初期値に戻しました。');
        } catch (RuntimeException $exception) {
            $this->addFlash($request->getSession(), 'admin_error', $exception->getMessage());
        }

        return $this->redirectToSettings();
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

    private function redirectToSettings(): Response
    {
        return new Response('', Response::HTTP_SEE_OTHER, ['Location' => '/admin/settings']);
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
