<?php

use App\Settings\SiteSettingsRepository;
use App\Tests\TestCase;
use Symfony\Component\Dotenv\Dotenv;

$parseSetupModeEnvLocalFixture = function (string $filename): array {
    if (!is_file($filename)) {
        return [];
    }
    $contents = file_get_contents($filename);
    if (!is_string($contents)) {
        throw new LogicException('環境設定ファイルを読み込めませんでした。');
    }

    return (new Dotenv())->parse($contents, '.env.local');
};

// このプロセスの実環境変数(getenv)を一時的に書き換える。CLOUD_MODEは
// Docker Composeの`app`サービスで実envとして渡されているため、
// 「未決定」の画面を検証するテストではこれで一時的に取り除く。
$withRealEnv = function (string $name, ?string $value, callable $fn): mixed {
    $original = getenv($name);
    if ($value === null) {
        putenv($name);
    } else {
        putenv($name . '=' . $value);
    }
    try {
        return $fn();
    } finally {
        if ($original === false) {
            putenv($name);
        } else {
            putenv($name . '=' . $original);
        }
    }
};

$withSetupModeEnvOverrides = function (array $overrides, callable $fn): mixed {
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

$cleanupSetupModeFixtures = function (SiteSettingsRepository $repository, string $envLocalFilename): void {
    $repository->resetAdminPasswordHash();
    if (is_file($envLocalFilename)) {
        unlink($envLocalFilename);
    }
    if (is_file($envLocalFilename . '.lock')) {
        unlink($envLocalFilename . '.lock');
    }
};

test('管理パスワード設定済みの場合はGET/POSTとも/adminへリダイレクトする', function () {
    /** @var TestCase $this */
    $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.20']);

    $client->request('GET', '/admin/setup/mode');
    $this->assertResponseRedirects('/admin', 303);

    $client->request('POST', '/admin/setup/mode', ['cloud_mode' => '0']);
    $this->assertResponseRedirects('/admin', 303);
});

test('CLOUD_MODEが実環境変数で固定されている場合は選択画面を出さず/admin/setupへ進む', function () use (
    $withRealEnv,
    $withSetupModeEnvOverrides,
    $cleanupSetupModeFixtures,
) {
    /** @var TestCase $this */
    $withRealEnv('CLOUD_MODE', '1', function () use ($withSetupModeEnvOverrides, $cleanupSetupModeFixtures) {
        $withSetupModeEnvOverrides(['ADMIN_PASSWORD_HASH' => ''], function () use ($cleanupSetupModeFixtures) {
            /** @var TestCase $this */
            $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.21']);
            $repository = $this->getContainer()->get(SiteSettingsRepository::class);
            $this->assertInstanceOf(SiteSettingsRepository::class, $repository);
            $envLocalFilename = $this->getContainer()->getParameter('app.env_local_filename');
            $this->assertIsString($envLocalFilename);
            $cleanupSetupModeFixtures($repository, $envLocalFilename);

            try {
                $client->request('GET', '/admin/setup/mode');
                $this->assertResponseRedirects('/admin/setup', 303);
            } finally {
                $cleanupSetupModeFixtures($repository, $envLocalFilename);
            }
        });
    });
});

test('未決定の場合は選択画面を表示し、ローカルモードを選ぶと決定して/admin/setupへ進む', function () use (
    $withRealEnv,
    $withSetupModeEnvOverrides,
    $parseSetupModeEnvLocalFixture,
    $cleanupSetupModeFixtures,
) {
    /** @var TestCase $this */
    $withRealEnv('CLOUD_MODE', null, function () use (
        $withSetupModeEnvOverrides,
        $parseSetupModeEnvLocalFixture,
        $cleanupSetupModeFixtures,
    ) {
        $withSetupModeEnvOverrides(['ADMIN_PASSWORD_HASH' => ''], function () use (
            $parseSetupModeEnvLocalFixture,
            $cleanupSetupModeFixtures,
        ) {
            /** @var TestCase $this */
            $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.22']);
            $repository = $this->getContainer()->get(SiteSettingsRepository::class);
            $this->assertInstanceOf(SiteSettingsRepository::class, $repository);
            $envLocalFilename = $this->getContainer()->getParameter('app.env_local_filename');
            $this->assertIsString($envLocalFilename);
            $cleanupSetupModeFixtures($repository, $envLocalFilename);

            try {
                $crawler = $client->request('GET', '/admin/setup/mode');
                $this->assertResponseIsSuccessful();
                $this->assertSelectorTextContains('h2', '動作モードの選択');

                $client->submit($crawler->selectButton('このモードで決定する')->form([
                    'cloud_mode' => '0',
                ]));

                $this->assertResponseRedirects('/admin/setup', 303);

                $environment = $parseSetupModeEnvLocalFixture($envLocalFilename);
                expect($environment)->toHaveKey('CLOUD_MODE')
                    ->and($environment['CLOUD_MODE'])->toBe('0');
            } finally {
                $cleanupSetupModeFixtures($repository, $envLocalFilename);
            }
        });
    });
});

test('クラウドモードを選んでも接続確認に失敗した場合はエラーを表示しCLOUD_MODEを書き込まない', function () use (
    $withRealEnv,
    $withSetupModeEnvOverrides,
    $parseSetupModeEnvLocalFixture,
    $cleanupSetupModeFixtures,
) {
    /** @var TestCase $this */
    $withRealEnv('CLOUD_MODE', null, function () use (
        $withSetupModeEnvOverrides,
        $parseSetupModeEnvLocalFixture,
        $cleanupSetupModeFixtures,
    ) {
        $withSetupModeEnvOverrides([
            'ADMIN_PASSWORD_HASH' => '',
            'VALKEY_URL' => 'redis://127.0.0.1:1',
            'ARCHIVE_S3_ENDPOINT' => 'http://127.0.0.1:1',
        ], function () use ($parseSetupModeEnvLocalFixture, $cleanupSetupModeFixtures) {
            /** @var TestCase $this */
            $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.23']);
            $repository = $this->getContainer()->get(SiteSettingsRepository::class);
            $this->assertInstanceOf(SiteSettingsRepository::class, $repository);
            $envLocalFilename = $this->getContainer()->getParameter('app.env_local_filename');
            $this->assertIsString($envLocalFilename);
            $cleanupSetupModeFixtures($repository, $envLocalFilename);

            try {
                $crawler = $client->request('GET', '/admin/setup/mode');
                $this->assertResponseIsSuccessful();

                $client->submit($crawler->selectButton('このモードで決定する')->form([
                    'cloud_mode' => '1',
                ]));

                $this->assertResponseIsSuccessful();
                $this->assertSelectorTextContains('main', '接続を確認できませんでした');
                $this->assertSelectorTextContains('main', 'Valkey');

                $environment = $parseSetupModeEnvLocalFixture($envLocalFilename);
                expect($environment)->not->toHaveKey('CLOUD_MODE');
            } finally {
                $cleanupSetupModeFixtures($repository, $envLocalFilename);
            }
        });
    });
});

test('すでに決定済みの場合はGETで/admin/setupへリダイレクトする', function () use (
    $withRealEnv,
    $withSetupModeEnvOverrides,
    $cleanupSetupModeFixtures,
) {
    /** @var TestCase $this */
    $withRealEnv('CLOUD_MODE', null, function () use ($withSetupModeEnvOverrides, $cleanupSetupModeFixtures) {
        $withSetupModeEnvOverrides(['ADMIN_PASSWORD_HASH' => ''], function () use ($cleanupSetupModeFixtures) {
            /** @var TestCase $this */
            $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.24']);
            $repository = $this->getContainer()->get(SiteSettingsRepository::class);
            $this->assertInstanceOf(SiteSettingsRepository::class, $repository);
            $envLocalFilename = $this->getContainer()->getParameter('app.env_local_filename');
            $this->assertIsString($envLocalFilename);
            $cleanupSetupModeFixtures($repository, $envLocalFilename);
            if (!is_dir(dirname($envLocalFilename))) {
                mkdir(dirname($envLocalFilename), 0775, true);
            }
            file_put_contents($envLocalFilename, "CLOUD_MODE='0'\n");

            try {
                $client->request('GET', '/admin/setup/mode');
                $this->assertResponseRedirects('/admin/setup', 303);
            } finally {
                $cleanupSetupModeFixtures($repository, $envLocalFilename);
            }
        });
    });
});

test('CSRFトークンが不正な場合はモードを決定しない', function () use (
    $withRealEnv,
    $withSetupModeEnvOverrides,
    $parseSetupModeEnvLocalFixture,
    $cleanupSetupModeFixtures,
) {
    /** @var TestCase $this */
    $withRealEnv('CLOUD_MODE', null, function () use (
        $withSetupModeEnvOverrides,
        $parseSetupModeEnvLocalFixture,
        $cleanupSetupModeFixtures,
    ) {
        $withSetupModeEnvOverrides(['ADMIN_PASSWORD_HASH' => ''], function () use (
            $parseSetupModeEnvLocalFixture,
            $cleanupSetupModeFixtures,
        ) {
            /** @var TestCase $this */
            $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.25']);
            $repository = $this->getContainer()->get(SiteSettingsRepository::class);
            $this->assertInstanceOf(SiteSettingsRepository::class, $repository);
            $envLocalFilename = $this->getContainer()->getParameter('app.env_local_filename');
            $this->assertIsString($envLocalFilename);
            $cleanupSetupModeFixtures($repository, $envLocalFilename);

            try {
                $client->request('POST', '/admin/setup/mode', [
                    '_token' => 'invalid-token',
                    'cloud_mode' => '0',
                ]);

                $this->assertResponseIsSuccessful();
                $this->assertSelectorTextContains('main', '入力の有効期限が切れました');

                $environment = $parseSetupModeEnvLocalFixture($envLocalFilename);
                expect($environment)->not->toHaveKey('CLOUD_MODE');
            } finally {
                $cleanupSetupModeFixtures($repository, $envLocalFilename);
            }
        });
    });
});

test('SETUP_TOKENが設定されている場合は一致しないと403になる', function () use (
    $withRealEnv,
    $withSetupModeEnvOverrides,
    $cleanupSetupModeFixtures,
) {
    /** @var TestCase $this */
    $withRealEnv('CLOUD_MODE', null, function () use ($withSetupModeEnvOverrides, $cleanupSetupModeFixtures) {
        $withSetupModeEnvOverrides([
            'ADMIN_PASSWORD_HASH' => '',
            'SETUP_TOKEN' => 'correct-token',
        ], function () use ($cleanupSetupModeFixtures) {
            /** @var TestCase $this */
            $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.26']);
            $repository = $this->getContainer()->get(SiteSettingsRepository::class);
            $this->assertInstanceOf(SiteSettingsRepository::class, $repository);
            $envLocalFilename = $this->getContainer()->getParameter('app.env_local_filename');
            $this->assertIsString($envLocalFilename);
            $cleanupSetupModeFixtures($repository, $envLocalFilename);

            try {
                $client->request('GET', '/admin/setup/mode?token=wrong-token');
                $this->assertResponseStatusCodeSame(403);

                $client->request('GET', '/admin/setup/mode?token=correct-token');
                $this->assertResponseIsSuccessful();
            } finally {
                $cleanupSetupModeFixtures($repository, $envLocalFilename);
            }
        });
    });
});
