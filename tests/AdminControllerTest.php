<?php

use App\Tests\TestCase;
use App\Settings\SiteSettings;
use App\Settings\SiteSettingsRepository;

test('管理画面は未認証時にログインフォームを表示する', function () {
    /** @var TestCase $this */
    $client = $this->createClient();
    $client->request('GET', '/admin');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextSame('h2', '管理画面ログイン');
    $this->assertSelectorExists('form[action="/admin"][method="post"] input[name="password"][type="password"]');
    $this->assertSelectorExists('input[name="_token"]');
    $this->assertSelectorTextNotContains('main', '現在作成中です。');
});

test('環境変数のパスワードで管理画面にログインしてログアウトできる', function () {
    /** @var TestCase $this */
    $client = $this->createClient();
    $crawler = $client->request('GET', '/admin');
    $token = $crawler->filter('input[name="_token"]')->attr('value');
    $client->request('POST', '/admin', [
        'password' => 'admin-test-password',
        '_token' => $token,
    ]);

    $this->assertResponseRedirects('/admin/settings', 303);
    $crawler = $client->followRedirect();
    $this->assertSelectorTextSame('h2', '設定管理');
    $this->assertSelectorExists('nav a[href="/admin/settings"][aria-current="page"]');
    $this->assertSelectorExists('nav a[href="/admin/posts"]');
    $this->assertSelectorTextSame('dt', '現在のAPP_ENV');
    $this->assertSelectorTextSame('.app-environment-value', 'test');
    $this->assertSelectorTextContains('main', 'クラウドモード');
    $this->assertSelectorTextSame('.cloud-mode-value', 'CLOUD_MODE=0');
    $this->assertSelectorTextContains('main', 'JSON Linesファイル');
    $this->assertSelectorTextContains('main', '日別JSON Linesファイル');
    $this->assertSelectorTextContains('main', 'ローカルキャッシュファイル');

    $client->submit($crawler->selectButton('ログアウト')->form());
    $this->assertResponseRedirects('/admin', 303);
    $client->followRedirect();
    $this->assertSelectorTextSame('h2', '管理画面ログイン');
});

test('誤ったパスワードでは管理画面を表示しない', function () {
    /** @var TestCase $this */
    $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.2']);
    $crawler = $client->request('GET', '/admin');
    $token = $crawler->filter('input[name="_token"]')->attr('value');
    $client->request('POST', '/admin', [
        'password' => 'wrong-password',
        '_token' => $token,
    ]);

    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextSame('[role="alert"]', 'パスワードが正しくありません。');
    $this->assertSelectorTextNotContains('main', '現在作成中です。');
});

test('管理画面でサイトタイトルを保存して初期値へ戻す', function () {
    /** @var TestCase $this */
    $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.3']);
    $settings = $this->getContainer()->get(SiteSettings::class);
    $this->assertInstanceOf(SiteSettings::class, $settings);
    $settings->resetTitle();

    try {
        $crawler = $client->request('GET', '/admin');
        $client->submit($crawler->selectButton('ログイン')->form([
            'password' => 'admin-test-password',
        ]));
        $crawler = $client->followRedirect();

        $this->assertInputValueSame('title', 'Open Kuzuha');
        $this->assertSelectorExists('input[name="title"][required][maxlength="100"]');
        $client->submit($crawler->selectButton('保存')->form([
            'title' => '管理画面で変更したタイトル',
        ]));
        $this->assertResponseRedirects('/admin/settings', 303);
        $crawler = $client->followRedirect();
        $this->assertSelectorTextSame('[role="status"]', 'サイトタイトルを保存しました。');
        $this->assertInputValueSame('title', '管理画面で変更したタイトル');

        $client->request('GET', '/');
        $this->assertSelectorTextContains('title', '管理画面で変更したタイトル');
        $this->assertSelectorTextContains('h1', '管理画面で変更したタイトル');

        $crawler = $client->request('GET', '/admin/settings');
        $client->submit($crawler->selectButton('初期値（Open Kuzuha）に戻す')->form());
        $this->assertResponseRedirects('/admin/settings', 303);
        $crawler = $client->followRedirect();
        $this->assertSelectorTextSame('[role="status"]', 'サイトタイトルを初期値に戻しました。');
        $this->assertInputValueSame('title', 'Open Kuzuha');
    } finally {
        $settings->resetTitle();
    }
});

test('未認証または不正な入力ではサイトタイトルを変更しない', function () {
    /** @var TestCase $this */
    $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.4']);
    $settings = $this->getContainer()->get(SiteSettings::class);
    $this->assertInstanceOf(SiteSettings::class, $settings);
    $settings->resetTitle();

    try {
        $client->request('POST', '/admin/settings/title', [
            'title' => '未認証の変更',
            '_token' => 'invalid',
        ]);
        $this->assertResponseRedirects('/admin', 303);
        expect($settings->title())->toBe('Open Kuzuha');

        $crawler = $client->request('GET', '/admin');
        $client->submit($crawler->selectButton('ログイン')->form([
            'password' => 'admin-test-password',
        ]));
        $crawler = $client->followRedirect();
        $token = $crawler->filter('form[action="/admin/settings/title"] input[name="_token"]')->attr('value');
        $client->request('POST', '/admin/settings/title', [
            'title' => str_repeat('あ', 101),
            '_token' => $token,
        ]);
        $crawler = $client->followRedirect();
        $this->assertSelectorTextSame('[role="alert"]', 'サイトタイトルは100文字以内で入力してください。');
        expect($settings->title())->toBe('Open Kuzuha');
    } finally {
        $settings->resetTitle();
    }
});

test('管理画面でマスターログ保存件数を変更して初期値へ戻す', function () {
    /** @var TestCase $this */
    $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.7']);
    $settings = $this->getContainer()->get(SiteSettings::class);
    $this->assertInstanceOf(SiteSettings::class, $settings);
    $settings->resetCentralPostLimit();

    try {
        $crawler = $client->request('GET', '/admin');
        $client->submit($crawler->selectButton('ログイン')->form(['password' => 'admin-test-password']));
        $crawler = $client->followRedirect();
        $this->assertInputValueSame('central_post_limit', '500');

        $client->submit($crawler->selectButton('保存して反映')->form(['central_post_limit' => '250']));
        $this->assertResponseRedirects('/admin/settings', 303);
        $crawler = $client->followRedirect();
        $this->assertSelectorTextSame('[role="status"]', 'マスターログ保存件数を保存し、現在のログへ反映しました。');
        $this->assertInputValueSame('central_post_limit', '250');
        expect($settings->centralPostLimit())->toBe(250);

        $client->submit($crawler->selectButton('初期値（500件）に戻す')->form());
        $crawler = $client->followRedirect();
        $this->assertInputValueSame('central_post_limit', '500');
    } finally {
        $settings->resetCentralPostLimit();
    }
});

test('管理画面で初期表示件数を変更して初期値へ戻す', function () {
    /** @var TestCase $this */
    $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.17']);
    $settings = $this->getContainer()->get(SiteSettings::class);
    $this->assertInstanceOf(SiteSettings::class, $settings);
    $settings->resetDefaultDisplayCount();

    try {
        $crawler = $client->request('GET', '/admin');
        $client->submit($crawler->selectButton('ログイン')->form(['password' => 'admin-test-password']));
        $crawler = $client->followRedirect();
        $this->assertInputValueSame('default_display_count', '40');

        $form = $crawler->filter('form[action="/admin/settings/default-display-count"]')->form([
            'default_display_count' => '25',
        ]);
        $client->submit($form);
        $crawler = $client->followRedirect();
        $this->assertSelectorTextSame('[role="status"]', '初期表示件数を保存しました。');
        $this->assertInputValueSame('default_display_count', '25');
        expect($settings->defaultDisplayCount())->toBe(25);

        $client->submit($crawler->selectButton('初期値（40件）に戻す')->form());
        $crawler = $client->followRedirect();
        $this->assertInputValueSame('default_display_count', '40');
    } finally {
        $settings->resetDefaultDisplayCount();
    }
});

test('管理画面で本文入力制限を変更して初期値へ戻す', function () {
    /** @var TestCase $this */
    $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.18']);
    $settings = $this->getContainer()->get(SiteSettings::class);
    $this->assertInstanceOf(SiteSettings::class, $settings);
    $settings->resetMaxMessageLines();
    $settings->resetMaxLineChars();
    $settings->resetMaxMessageChars();

    try {
        $crawler = $client->request('GET', '/admin');
        $client->submit($crawler->selectButton('ログイン')->form(['password' => 'admin-test-password']));
        $crawler = $client->followRedirect();
        $this->assertInputValueSame('max_line_chars', '200');
        $this->assertInputValueSame('max_message_lines', '50');
        $this->assertInputValueSame('max_message_chars', '8400');

        $form = $crawler->filter('form[action="/admin/settings/message-limits"]')->form([
            'max_line_chars' => '120',
            'max_message_lines' => '30',
            'max_message_chars' => '4000',
        ]);
        $client->submit($form);
        $crawler = $client->followRedirect();
        $this->assertSelectorTextSame('[role="status"]', '本文入力制限を保存しました。');
        $this->assertInputValueSame('max_line_chars', '120');
        $this->assertInputValueSame('max_message_lines', '30');
        $this->assertInputValueSame('max_message_chars', '4000');
        expect($settings->maxLineChars())->toBe(120)
            ->and($settings->maxMessageLines())->toBe(30)
            ->and($settings->maxMessageChars())->toBe(4000);

        $client->submit($crawler->selectButton('初期値（全体8400文字・1行200文字・50行）に戻す')->form());
        $crawler = $client->followRedirect();
        $this->assertInputValueSame('max_line_chars', '200');
        $this->assertInputValueSame('max_message_lines', '50');
        $this->assertInputValueSame('max_message_chars', '8400');
    } finally {
        $settings->resetMaxMessageLines();
        $settings->resetMaxLineChars();
        $settings->resetMaxMessageChars();
    }
});

test('管理画面で参加者カウント有効時間を変更して初期値へ戻す', function () {
    /** @var TestCase $this */
    $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.20']);
    $settings = $this->getContainer()->get(SiteSettings::class);
    $this->assertInstanceOf(SiteSettings::class, $settings);
    $settings->resetVisitorActiveSeconds();

    try {
        $crawler = $client->request('GET', '/admin');
        $client->submit($crawler->selectButton('ログイン')->form(['password' => 'admin-test-password']));
        $crawler = $client->followRedirect();
        $this->assertInputValueSame('visitor_active_seconds', '300');

        $form = $crawler->filter('form[action="/admin/settings/visitor-active-seconds"]')->form([
            'visitor_active_seconds' => '600',
        ]);
        $client->submit($form);
        $crawler = $client->followRedirect();
        $this->assertSelectorTextSame('[role="status"]', '参加者カウント有効時間を保存しました。');
        $this->assertInputValueSame('visitor_active_seconds', '600');
        expect($settings->visitorActiveSeconds())->toBe(600);

        $client->submit($crawler->selectButton('初期値（300秒）に戻す')->form());
        $crawler = $client->followRedirect();
        $this->assertInputValueSame('visitor_active_seconds', '300');
    } finally {
        $settings->resetVisitorActiveSeconds();
    }
});

test('管理画面でサービス開始日を変更して初期値へ戻す', function () {
    /** @var TestCase $this */
    $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.21']);
    $settings = $this->getContainer()->get(SiteSettings::class);
    $this->assertInstanceOf(SiteSettings::class, $settings);
    $settings->resetServiceStartedAt();

    try {
        $crawler = $client->request('GET', '/admin');
        $client->submit($crawler->selectButton('ログイン')->form(['password' => 'admin-test-password']));
        $crawler = $client->followRedirect();
        $this->assertInputValueSame('service_started_at', '2026-08-12');

        $form = $crawler->filter('form[action="/admin/settings/service-started-at"]')->form([
            'service_started_at' => '2025-04-01',
        ]);
        $client->submit($form);
        $crawler = $client->followRedirect();
        $this->assertSelectorTextSame('[role="status"]', 'サービス開始日を保存しました。');
        $this->assertInputValueSame('service_started_at', '2025-04-01');

        $client->request('GET', '/');
        $this->assertSelectorTextContains('.page-view-counter', '2025/04/01 から');

        $crawler = $client->request('GET', '/admin/settings');
        $client->submit($crawler->selectButton('初期値（2026/08/12）に戻す')->form());
        $crawler = $client->followRedirect();
        $this->assertInputValueSame('service_started_at', '2026-08-12');
    } finally {
        $settings->resetServiceStartedAt();
    }
});

test('管理画面で管理者情報を変更して初期値へ戻す', function () {
    /** @var TestCase $this */
    $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.22']);
    $settings = $this->getContainer()->get(SiteSettings::class);
    $this->assertInstanceOf(SiteSettings::class, $settings);
    $settings->resetAdminName();
    $settings->resetAdminEmail();

    try {
        $crawler = $client->request('GET', '/admin');
        $client->submit($crawler->selectButton('ログイン')->form(['password' => 'admin-test-password']));
        $crawler = $client->followRedirect();
        $this->assertInputValueSame('admin_name', '管理人');
        $this->assertInputValueSame('admin_email', '');

        $form = $crawler->filter('form[action="/admin/settings/admin-identity"]')->form([
            'admin_name' => '掲示板管理者',
            'admin_email' => 'admin@example.com',
        ]);
        $client->submit($form);
        $crawler = $client->followRedirect();
        $this->assertSelectorTextSame('[role="status"]', '管理者情報を保存しました。');
        $this->assertInputValueSame('admin_name', '掲示板管理者');
        $this->assertInputValueSame('admin_email', 'admin@example.com');

        $client->request('GET', '/');
        $this->assertSelectorExists('a.admin-contact-link[href="mailto:admin@example.com"]');

        $crawler = $client->request('GET', '/admin/settings');
        $client->submit($crawler->selectButton('初期値（管理人・メール未設定）に戻す')->form());
        $crawler = $client->followRedirect();
        $this->assertInputValueSame('admin_name', '管理人');
        $this->assertInputValueSame('admin_email', '');
    } finally {
        $settings->resetAdminName();
        $settings->resetAdminEmail();
    }
});

test('管理画面で投稿禁止ワードを変更して初期値へ戻す', function () {
    /** @var TestCase $this */
    $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.23']);
    $settings = $this->getContainer()->get(SiteSettings::class);
    $this->assertInstanceOf(SiteSettings::class, $settings);
    $settings->resetProhibitedWords();

    try {
        $crawler = $client->request('GET', '/admin');
        $client->submit($crawler->selectButton('ログイン')->form(['password' => 'admin-test-password']));
        $crawler = $client->followRedirect();
        expect($crawler->filter('textarea[name="prohibited_words"]')->html(''))->toBe('');

        $form = $crawler->filter('form[action="/admin/settings/prohibited-words"]')->form([
            'prohibited_words' => "禁止語\n別の語",
        ]);
        $client->submit($form);
        $crawler = $client->followRedirect();
        $this->assertSelectorTextSame('[role="status"]', '投稿禁止ワードを保存しました。');
        expect($crawler->filter('textarea[name="prohibited_words"]')->html())->toBe("禁止語\n別の語");
        expect($settings->prohibitedWords())->toBe(['禁止語', '別の語']);

        $client->submit($crawler->selectButton('初期値（未設定）に戻す')->form());
        $crawler = $client->followRedirect();
        expect($crawler->filter('textarea[name="prohibited_words"]')->html(''))->toBe('');
    } finally {
        $settings->resetProhibitedWords();
    }
});

test('管理画面で投稿禁止IPアドレスとCIDRを変更して初期値へ戻す', function () {
    /** @var TestCase $this */
    $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.29']);
    $settings = $this->getContainer()->get(SiteSettings::class);
    $this->assertInstanceOf(SiteSettings::class, $settings);
    $settings->resetDeniedPostNetworks();

    try {
        $crawler = $client->request('GET', '/admin');
        $client->submit($crawler->selectButton('ログイン')->form(['password' => 'admin-test-password']));
        $crawler = $client->followRedirect();
        expect($crawler->filter('textarea[name="denied_post_networks"]')->html(''))->toBe('');

        $form = $crawler->filter('form[action="/admin/settings/denied-post-networks"]')->form([
            'denied_post_networks' => "192.0.2.10\n198.51.100.0/24\n2001:db8::/32",
        ]);
        $client->submit($form);
        $crawler = $client->followRedirect();
        $this->assertSelectorTextSame('[role="status"]', '投稿禁止IPアドレス・CIDRを保存しました。');
        expect($settings->deniedPostNetworks())->toBe(['192.0.2.10', '198.51.100.0/24', '2001:db8::/32']);

        $form = $crawler->filter('form[action="/admin/settings/denied-post-networks/reset"]')->form();
        $client->submit($form);
        $crawler = $client->followRedirect();
        expect($crawler->filter('textarea[name="denied_post_networks"]')->html(''))->toBe('');
    } finally {
        $settings->resetDeniedPostNetworks();
    }
});

test('投稿禁止IPアドレス設定の不正なCIDRを拒否する', function () {
    /** @var TestCase $this */
    $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.30']);
    $settings = $this->getContainer()->get(SiteSettings::class);
    $this->assertInstanceOf(SiteSettings::class, $settings);
    $settings->resetDeniedPostNetworks();

    try {
        $crawler = $client->request('GET', '/admin');
        $client->submit($crawler->selectButton('ログイン')->form(['password' => 'admin-test-password']));
        $crawler = $client->followRedirect();
        $form = $crawler->filter('form[action="/admin/settings/denied-post-networks"]')->form([
            'denied_post_networks' => "192.0.2.0/33\nexample.com",
        ]);
        $client->submit($form);
        $client->followRedirect();

        $this->assertSelectorTextContains('[role="alert"]', '1行目のCIDRプレフィックス長が不正です。');
        expect($settings->deniedPostNetworks())->toBe([]);
    } finally {
        $settings->resetDeniedPostNetworks();
    }
});

test('管理画面でアクセス禁止IPアドレスを設定しても管理画面から復旧できる', function () {
    /** @var TestCase $this */
    $client = $this->createClient([], ['REMOTE_ADDR' => '203.0.113.31']);
    $settings = $this->getContainer()->get(SiteSettings::class);
    $this->assertInstanceOf(SiteSettings::class, $settings);
    $settings->resetDeniedAccessNetworks();

    try {
        $crawler = $client->request('GET', '/admin');
        $client->submit($crawler->selectButton('ログイン')->form(['password' => 'admin-test-password']));
        $crawler = $client->followRedirect();
        $this->assertSelectorExists('[aria-describedby="denied-access-networks-help"]');

        $form = $crawler->filter('form[action="/admin/settings/denied-access-networks"]')->form([
            'denied_access_networks' => '203.0.113.0/24',
        ]);
        $client->submit($form);
        $crawler = $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextSame('[role="status"]', 'アクセス禁止IPアドレス・CIDRを保存しました。');

        $client->request('GET', '/');
        $this->assertResponseStatusCodeSame(403);

        $crawler = $client->request('GET', '/admin/settings');
        $this->assertResponseIsSuccessful();
        $form = $crawler->filter('form[action="/admin/settings/denied-access-networks/reset"]')->form();
        $client->submit($form);
        $client->followRedirect();
        expect($settings->deniedAccessNetworks())->toBe([]);
    } finally {
        $settings->resetDeniedAccessNetworks();
    }
});

test('管理画面でメンテナンスモードを設定しても管理画面から復旧できる', function () {
    /** @var TestCase $this */
    $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.32']);
    $settings = $this->getContainer()->get(SiteSettings::class);
    $this->assertInstanceOf(SiteSettings::class, $settings);
    $settings->resetOperationStatus();

    try {
        $crawler = $client->request('GET', '/admin');
        $client->submit($crawler->selectButton('ログイン')->form(['password' => 'admin-test-password']));
        $crawler = $client->followRedirect();
        $form = $crawler->filter('form[action="/admin/settings/operation-status"]')->form([
            'posting_enabled' => '1',
            'maintenance_enabled' => '1',
            'maintenance_message' => 'ただいま更新作業中です。',
            'maintenance_ends_at' => '2030-01-02T03:04',
        ]);
        $client->submit($form);
        $client->followRedirect();
        $this->assertSelectorTextSame('[role="status"]', '運用状態を保存しました。');
        expect($settings->maintenanceEnabled())->toBeTrue();
        expect($settings->maintenanceEndsAt())->not->toBeNull();

        $client->request('GET', '/');
        $this->assertResponseStatusCodeSame(503);
        expect($client->getResponse()->headers->get('Retry-After'))->toBe('Wed, 02 Jan 2030 03:04:00 GMT');
        $this->assertSelectorTextContains('main', 'ただいま更新作業中です。');

        $crawler = $client->request('GET', '/admin/settings');
        $this->assertResponseIsSuccessful();
        $client->submit($crawler->filter('form[action="/admin/settings/operation-status/reset"]')->form());
        $client->followRedirect();
        expect($settings->maintenanceEnabled())->toBeFalse();
    } finally {
        $settings->resetOperationStatus();
    }
});

test('管理画面で投稿者による削除設定を変更して初期値へ戻す', function () {
    /** @var TestCase $this */
    $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.25']);
    $settings = $this->getContainer()->get(SiteSettings::class);
    $this->assertInstanceOf(SiteSettings::class, $settings);
    $settings->resetUndoSettings();

    try {
        $crawler = $client->request('GET', '/admin');
        $client->submit($crawler->selectButton('ログイン')->form(['password' => 'admin-test-password']));
        $crawler = $client->followRedirect();
        $this->assertCheckboxChecked('undo_enabled');
        $this->assertInputValueSame('undo_window_seconds', '86400');

        $form = $crawler->filter('form[action="/admin/settings/undo"]')->form([
            'undo_enabled' => false,
            'undo_window_seconds' => '3600',
        ]);
        $client->submit($form);
        $crawler = $client->followRedirect();
        $this->assertSelectorTextSame('[role="status"]', '投稿者による削除設定を保存しました。');
        $this->assertCheckboxNotChecked('undo_enabled');
        $this->assertInputValueSame('undo_window_seconds', '3600');

        $client->submit($crawler->selectButton('初期値（有効・86400秒）に戻す')->form());
        $crawler = $client->followRedirect();
        $this->assertCheckboxChecked('undo_enabled');
        $this->assertInputValueSame('undo_window_seconds', '86400');
    } finally {
        $settings->resetUndoSettings();
    }
});

test('管理画面で過去ログ保持日数を変更して初期値へ戻す', function () {
    /** @var TestCase $this */
    $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.27']);
    $settings = $this->getContainer()->get(SiteSettings::class);
    $this->assertInstanceOf(SiteSettings::class, $settings);
    $settings->resetArchiveRetentionDays();

    try {
        $crawler = $client->request('GET', '/admin');
        $client->submit($crawler->selectButton('ログイン')->form(['password' => 'admin-test-password']));
        $crawler = $client->followRedirect();
        $this->assertInputValueSame('archive_retention_days', '0');

        $form = $crawler->filter('form[action="/admin/settings/archive-retention"]')->form([
            'archive_retention_days' => '0',
        ]);
        $client->submit($form);
        $crawler = $client->followRedirect();
        $this->assertSelectorTextSame('[role="status"]', '過去ログ保持日数を無期限で保存しました。');
        $this->assertInputValueSame('archive_retention_days', '0');

        $client->submit($crawler->selectButton('初期値（無期限）に戻す')->form());
        $crawler = $client->followRedirect();
        $this->assertInputValueSame('archive_retention_days', '0');
    } finally {
        $settings->resetArchiveRetentionDays();
    }
});

test('管理画面で過去ログ公開日数を変更して初期値へ戻す', function () {
    /** @var TestCase $this */
    $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.28']);
    $settings = $this->getContainer()->get(SiteSettings::class);
    $this->assertInstanceOf(SiteSettings::class, $settings);
    $settings->resetArchivePublicDays();

    try {
        $crawler = $client->request('GET', '/admin');
        $client->submit($crawler->selectButton('ログイン')->form(['password' => 'admin-test-password']));
        $crawler = $client->followRedirect();
        $this->assertInputValueSame('archive_public_days', '30');

        $form = $crawler->filter('form[action="/admin/settings/archive-public-days"]')->form([
            'archive_public_days' => '90',
        ]);
        $client->submit($form);
        $crawler = $client->followRedirect();
        $this->assertSelectorTextSame('[role="status"]', '過去ログ公開日数を保存しました。');
        $this->assertInputValueSame('archive_public_days', '90');

        $client->submit($crawler->selectButton('初期値（30日）に戻す')->form());
        $crawler = $client->followRedirect();
        $this->assertInputValueSame('archive_public_days', '30');
    } finally {
        $settings->resetArchivePublicDays();
    }
});

test('投稿記事管理画面は認証必須でWIPを表示する', function () {
    /** @var TestCase $this */
    $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.6']);
    $client->request('GET', '/admin/posts');
    $this->assertResponseRedirects('/admin', 303);

    $crawler = $client->request('GET', '/admin');
    $client->submit($crawler->selectButton('ログイン')->form([
        'password' => 'admin-test-password',
    ]));
    $client->request('GET', '/admin/posts');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextSame('h2', '投稿記事管理');
    $this->assertSelectorTextContains('main', '投稿記事管理は現在作成中です。');
    $this->assertSelectorExists('nav a[href="/admin/posts"][aria-current="page"]');
});

test('管理画面でパスワードを変更すると再ログインが必要になる', function () {
    /** @var TestCase $this */
    $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.5']);
    $repository = $this->getContainer()->get(SiteSettingsRepository::class);
    $this->assertInstanceOf(SiteSettingsRepository::class, $repository);
    $repository->resetAdminPasswordHash();

    try {
        $crawler = $client->request('GET', '/admin');
        $client->submit($crawler->selectButton('ログイン')->form([
            'password' => 'admin-test-password',
        ]));
        $crawler = $client->followRedirect();

        $this->assertSelectorExists('form[action="/admin/settings/password"]');
        $client->submit($crawler->selectButton('パスワードを変更')->form([
            'current_password' => 'admin-test-password',
            'new_password' => 'changed-test-password',
            'new_password_confirmation' => 'changed-test-password',
        ]));
        $this->assertResponseRedirects('/admin', 303);
        $client->followRedirect();
        $this->assertSelectorTextSame('h2', '管理画面ログイン');

        $crawler = $client->request('GET', '/admin');
        $client->submit($crawler->selectButton('ログイン')->form([
            'password' => 'changed-test-password',
        ]));
        $this->assertResponseRedirects('/admin/settings', 303);
    } finally {
        $repository->resetAdminPasswordHash();
    }
});
