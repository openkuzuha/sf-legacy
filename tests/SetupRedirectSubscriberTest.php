<?php

use App\Settings\SiteSettingsRepository;
use App\Tests\TestCase;

// このプロセスの実環境変数(getenv)を一時的に書き換える。CLOUD_MODEは
// Docker Composeの`app`サービスで実envとして渡されているため、
// 「未決定」の誘導先を検証するテストではこれで一時的に取り除く。
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

$withUnconfiguredAdminPassword = function (callable $fn): mixed {
    $originalEnv = $_ENV['ADMIN_PASSWORD_HASH'] ?? null;
    $originalServer = $_SERVER['ADMIN_PASSWORD_HASH'] ?? null;
    $_ENV['ADMIN_PASSWORD_HASH'] = '';
    $_SERVER['ADMIN_PASSWORD_HASH'] = '';

    try {
        return $fn();
    } finally {
        if ($originalEnv === null) {
            unset($_ENV['ADMIN_PASSWORD_HASH']);
        } else {
            $_ENV['ADMIN_PASSWORD_HASH'] = $originalEnv;
        }
        if ($originalServer === null) {
            unset($_SERVER['ADMIN_PASSWORD_HASH']);
        } else {
            $_SERVER['ADMIN_PASSWORD_HASH'] = $originalServer;
        }
    }
};

test('管理パスワード未設定時はトップページからセットアップへ誘導する', function () use ($withUnconfiguredAdminPassword) {
    /** @var TestCase $this */
    $withUnconfiguredAdminPassword(function () {
        /** @var TestCase $this */
        $client = $this->createClient();
        $repository = $this->getContainer()->get(SiteSettingsRepository::class);
        $this->assertInstanceOf(SiteSettingsRepository::class, $repository);
        $repository->resetAdminPasswordHash();

        try {
            $client->request('GET', '/');
            $this->assertResponseRedirects('/admin/setup', 303);

            $client->request('GET', '/archive');
            $this->assertResponseRedirects('/admin/setup', 303);
        } finally {
            $repository->resetAdminPasswordHash();
        }
    });
});

test('未設定時もセットアップ画面自身は表示しトークンを引き継ぐ', function () use ($withUnconfiguredAdminPassword) {
    /** @var TestCase $this */
    $withUnconfiguredAdminPassword(function () {
        /** @var TestCase $this */
        $client = $this->createClient();
        $repository = $this->getContainer()->get(SiteSettingsRepository::class);
        $this->assertInstanceOf(SiteSettingsRepository::class, $repository);
        $repository->resetAdminPasswordHash();

        try {
            $client->request('GET', '/?token=setup%20token');
            $this->assertResponseRedirects('/admin/setup?token=setup+token', 303);

            $client->request('GET', '/admin/setup');
            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextSame('h2', '初回セットアップ');
        } finally {
            $repository->resetAdminPasswordHash();
        }
    });
});

test('動作モード未決定時はトップページからモード選択画面へ誘導する', function () use (
    $withRealEnv,
    $withUnconfiguredAdminPassword,
) {
    /** @var TestCase $this */
    $withRealEnv('CLOUD_MODE', null, function () use ($withUnconfiguredAdminPassword) {
        $withUnconfiguredAdminPassword(function () {
            /** @var TestCase $this */
            $client = $this->createClient();
            $repository = $this->getContainer()->get(SiteSettingsRepository::class);
            $this->assertInstanceOf(SiteSettingsRepository::class, $repository);
            $repository->resetAdminPasswordHash();
            $envLocalFilename = $this->getContainer()->getParameter('app.env_local_filename');
            $this->assertIsString($envLocalFilename);
            if (is_file($envLocalFilename)) {
                unlink($envLocalFilename);
            }

            try {
                $client->request('GET', '/?token=setup%20token');
                $this->assertResponseRedirects('/admin/setup/mode?token=setup+token', 303);

                $client->request('GET', '/admin/setup/mode');
                $this->assertResponseIsSuccessful();
                $this->assertSelectorTextSame('h2', '動作モードの選択');
            } finally {
                $repository->resetAdminPasswordHash();
                if (is_file($envLocalFilename)) {
                    unlink($envLocalFilename);
                }
                if (is_file($envLocalFilename . '.lock')) {
                    unlink($envLocalFilename . '.lock');
                }
            }
        });
    });
});
