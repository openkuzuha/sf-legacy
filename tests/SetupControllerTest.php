<?php

use App\Settings\SiteSettingsRepository;
use App\Tests\TestCase;
use Symfony\Component\Dotenv\Dotenv;

$parseSetupEnvLocalFixture = function (string $filename): array {
    $contents = file_get_contents($filename);
    if (!is_string($contents)) {
        throw new LogicException('環境設定ファイルを読み込めませんでした。');
    }

    return (new Dotenv())->parse($contents, '.env.local');
};

$withSetupEnvOverrides = function (array $overrides, callable $fn): mixed {
    $originals = [];
    foreach ($overrides as $name => $value) {
        $originals[$name] = ['env' => $_ENV[$name] ?? null, 'server' => $_SERVER[$name] ?? null];
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
    try {
        return $fn();
    } finally {
        foreach ($originals as $name => $original) {
            if ($original['env'] === null) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $original['env'];
            }
            if ($original['server'] === null) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $original['server'];
            }
        }
    }
};

$cleanupSetupControllerFixtures = function (SiteSettingsRepository $repository, string $envLocalFilename): void {
    $repository->resetAdminPasswordHash();
    $repository->resetTitle();
    $repository->resetAdminName();
    $repository->resetAdminEmail();
    $repository->resetServiceStartedAt();
    if (is_file($envLocalFilename)) {
        unlink($envLocalFilename);
    }
    if (is_file($envLocalFilename . '.lock')) {
        unlink($envLocalFilename . '.lock');
    }
};

test('管理パスワード設定済みの場合はGET/POSTとも/adminへリダイレクトする', function () {
    /** @var TestCase $this */
    $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.10']);

    $client->request('GET', '/admin/setup');
    $this->assertResponseRedirects('/admin', 303);

    $client->request('POST', '/admin/setup', ['title' => '乗っ取り']);
    $this->assertResponseRedirects('/admin', 303);
});

test('有効な入力でセットアップを完了しAPP_SECRETとAUDIT_HMAC_KEYを.env.localへ書き込む', function () use (
    $parseSetupEnvLocalFixture,
    $withSetupEnvOverrides,
    $cleanupSetupControllerFixtures,
) {
    /** @var TestCase $this */
    $withSetupEnvOverrides(['ADMIN_PASSWORD_HASH' => '', 'AUDIT_HMAC_KEY' => ''], function () use (
        $parseSetupEnvLocalFixture,
        $cleanupSetupControllerFixtures,
    ) {
        /** @var TestCase $this */
        $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.11']);
        $repository = $this->getContainer()->get(SiteSettingsRepository::class);
        $this->assertInstanceOf(SiteSettingsRepository::class, $repository);
        $envLocalFilename = $this->getContainer()->getParameter('app.env_local_filename');
        $this->assertIsString($envLocalFilename);
        $cleanupSetupControllerFixtures($repository, $envLocalFilename);

        try {
            $crawler = $client->request('GET', '/admin/setup');
            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('main', '自動生成して.env.localへ保存します');

            $client->submit($crawler->selectButton('セットアップを完了する')->form([
                'title' => 'セットアップ済み掲示板',
                'admin_name' => 'せっとあっぷ管理人',
                'admin_email' => 'admin@example.test',
                'service_started_at' => '2020-01-01',
                'password' => 'setup-secure-password',
                'password_confirmation' => 'setup-secure-password',
            ]));

            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('main', 'セットアップが完了しました');

            expect($repository->title())->toBe('セットアップ済み掲示板')
                ->and($repository->adminName())->toBe('せっとあっぷ管理人')
                ->and($repository->adminEmail())->toBe('admin@example.test')
                ->and($repository->serviceStartedAt())->toBe('2020-01-01');

            $this->assertTrue(is_file($envLocalFilename));
            $environment = $parseSetupEnvLocalFixture($envLocalFilename);
            expect($environment)->toHaveKeys(['APP_SECRET', 'AUDIT_HMAC_KEY']);
            expect($environment['APP_SECRET'])->toBeString();
            expect($environment['APP_SECRET'])->not->toBe('');
            expect($environment['AUDIT_HMAC_KEY'])->toBeString();
            expect($environment['AUDIT_HMAC_KEY'])->not->toBe('');

            // 設定済みパスワードでログインできることを確認する。
            $loginCrawler = $client->request('GET', '/admin');
            $this->assertResponseIsSuccessful();
            $client->submit($loginCrawler->selectButton('ログイン')->form([
                'password' => 'setup-secure-password',
            ]));
            $this->assertResponseRedirects('/admin/settings', 303);
        } finally {
            $cleanupSetupControllerFixtures($repository, $envLocalFilename);
        }
    });
});

test('セットアップ画面のサービス開始日は既定で本日の日付を表示する', function () use ($withSetupEnvOverrides) {
    /** @var TestCase $this */
    $withSetupEnvOverrides(['ADMIN_PASSWORD_HASH' => ''], function () {
        /** @var TestCase $this */
        $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.15']);
        $repository = $this->getContainer()->get(SiteSettingsRepository::class);
        $this->assertInstanceOf(SiteSettingsRepository::class, $repository);
        $timezone = $this->getContainer()->getParameter('app.timezone');
        $this->assertIsString($timezone);
        $repository->resetAdminPasswordHash();
        $repository->resetServiceStartedAt();

        try {
            $crawler = $client->request('GET', '/admin/setup');
            $this->assertResponseIsSuccessful();

            $today = (new DateTimeImmutable('today', new DateTimeZone($timezone)))->format('Y-m-d');
            $this->assertSame($today, $crawler->filter('#setup-service-started-at')->attr('value'));
        } finally {
            $repository->resetAdminPasswordHash();
            $repository->resetServiceStartedAt();
        }
    });
});

test('サービス開始日に不正な形式を入力するとセットアップを完了しない', function () use ($withSetupEnvOverrides) {
    /** @var TestCase $this */
    $withSetupEnvOverrides(['ADMIN_PASSWORD_HASH' => ''], function () {
        /** @var TestCase $this */
        $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.16']);
        $repository = $this->getContainer()->get(SiteSettingsRepository::class);
        $this->assertInstanceOf(SiteSettingsRepository::class, $repository);
        $repository->resetAdminPasswordHash();
        $repository->resetServiceStartedAt();

        try {
            $crawler = $client->request('GET', '/admin/setup');
            $client->submit($crawler->selectButton('セットアップを完了する')->form([
                'service_started_at' => 'not-a-date',
                'password' => 'setup-secure-password',
                'password_confirmation' => 'setup-secure-password',
            ]));

            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('main', 'サービス開始日を正しい日付で入力してください');
            $this->assertFalse($repository->adminPasswordHash() !== null);
        } finally {
            $repository->resetAdminPasswordHash();
            $repository->resetServiceStartedAt();
        }
    });
});

test('SETUP_TOKENが設定されている場合は一致しないと403になる', function () use ($withSetupEnvOverrides) {
    /** @var TestCase $this */
    $withSetupEnvOverrides(['ADMIN_PASSWORD_HASH' => '', 'SETUP_TOKEN' => 'correct-token'], function () {
        /** @var TestCase $this */
        $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.12']);
        $repository = $this->getContainer()->get(SiteSettingsRepository::class);
        $this->assertInstanceOf(SiteSettingsRepository::class, $repository);
        $repository->resetAdminPasswordHash();

        try {
            $client->request('GET', '/admin/setup');
            $this->assertResponseStatusCodeSame(403);

            $client->request('GET', '/admin/setup?token=wrong-token');
            $this->assertResponseStatusCodeSame(403);

            $client->request('GET', '/admin/setup?token=correct-token');
            $this->assertResponseIsSuccessful();
        } finally {
            $repository->resetAdminPasswordHash();
        }
    });
});

test('SETUP_TOKENの誤り連投はレート制限される', function () use ($withSetupEnvOverrides) {
    /** @var TestCase $this */
    $withSetupEnvOverrides(['ADMIN_PASSWORD_HASH' => '', 'SETUP_TOKEN' => 'correct-token'], function () {
        /** @var TestCase $this */
        $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.13']);
        $repository = $this->getContainer()->get(SiteSettingsRepository::class);
        $this->assertInstanceOf(SiteSettingsRepository::class, $repository);
        $repository->resetAdminPasswordHash();

        try {
            for ($i = 0; $i < 5; $i++) {
                $client->request('GET', '/admin/setup?token=wrong-token');
                $this->assertResponseStatusCodeSame(403);
            }
            $client->request('GET', '/admin/setup?token=correct-token');
            $this->assertResponseStatusCodeSame(403);
            $this->assertStringContainsString('試行回数が多すぎます', (string) $client->getResponse()->getContent());
        } finally {
            $repository->resetAdminPasswordHash();
        }
    });
});

test('CSRFトークンが不正な場合はセットアップを完了しない', function () use ($withSetupEnvOverrides) {
    /** @var TestCase $this */
    $withSetupEnvOverrides(['ADMIN_PASSWORD_HASH' => ''], function () {
        /** @var TestCase $this */
        $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.14']);
        $repository = $this->getContainer()->get(SiteSettingsRepository::class);
        $this->assertInstanceOf(SiteSettingsRepository::class, $repository);
        $repository->resetAdminPasswordHash();

        try {
            $client->request('POST', '/admin/setup', [
                '_token' => 'invalid-token',
                'password' => 'setup-secure-password',
                'password_confirmation' => 'setup-secure-password',
            ]);

            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('main', '入力の有効期限が切れました');
            $this->assertFalse($repository->adminPasswordHash() !== null);
        } finally {
            $repository->resetAdminPasswordHash();
        }
    });
});
