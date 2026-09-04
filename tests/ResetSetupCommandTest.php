<?php

use App\Command\ResetSetupCommand;
use App\Settings\EnvLocalWriter;
use App\Settings\SiteSettingsRepository;
use App\Tests\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Dotenv\Dotenv;

$parseEnvLocalFixture = function (string $filename): array {
    $contents = file_get_contents($filename);
    if (!is_string($contents)) {
        throw new LogicException('環境設定ファイルを読み込めませんでした。');
    }

    return (new Dotenv())->parse($contents, '.env.local');
};

$cleanupResetSetupFixture = function (SiteSettingsRepository $repository, string $envLocalFilename): void {
    $repository->resetAdminPasswordHash();
    $repository->resetTitle();
    $repository->resetAdminName();
    $repository->resetAdminEmail();
    if (is_file($envLocalFilename)) {
        unlink($envLocalFilename);
    }
    if (is_file($envLocalFilename . '.lock')) {
        unlink($envLocalFilename . '.lock');
    }
};

test('セットアップ状態と生成済みシークレットを初期化する', function () use ($parseEnvLocalFixture, $cleanupResetSetupFixture) {
    /** @var TestCase $this */
    $repository = $this->getContainer()->get(SiteSettingsRepository::class);
    $this->assertInstanceOf(SiteSettingsRepository::class, $repository);

    $envLocalFilename = sys_get_temp_dir() . '/sf-legacy-reset-setup-' . bin2hex(random_bytes(8)) . '/.env.local';
    mkdir(dirname($envLocalFilename), 0775, true);
    $envLocalContents = "SOME_OTHER_VAR='keep-me'\nADMIN_PASSWORD_HASH='old-hash'\n"
        . "APP_SECRET='old-secret'\nAUDIT_HMAC_KEY='old-audit-key'\n";
    file_put_contents($envLocalFilename, $envLocalContents);

    $repository->setTitle('セットアップ済み掲示板');
    $repository->setAdminName('せっとあっぷ管理人');
    $repository->setAdminEmail('admin@example.test');
    $repository->setAdminPasswordHash(password_hash('setup-secure-password', PASSWORD_DEFAULT));

    try {
        $tester = new CommandTester(new ResetSetupCommand($repository, new EnvLocalWriter($envLocalFilename)));
        $status = $tester->execute(['--force' => true]);

        expect($status)->toBe(Command::SUCCESS);
        expect($tester->getDisplay())->toContain('セットアップ状態を初期化しました');

        expect($repository->title())->toBeNull()
            ->and($repository->adminName())->toBeNull()
            ->and($repository->adminEmail())->toBeNull()
            ->and($repository->adminPasswordHash())->toBeNull();

        $environment = $parseEnvLocalFixture($envLocalFilename);
        expect($environment)->toHaveKey('SOME_OTHER_VAR')
            ->and($environment)->not->toHaveKey('ADMIN_PASSWORD_HASH')
            ->and($environment)->not->toHaveKey('APP_SECRET')
            ->and($environment)->not->toHaveKey('AUDIT_HMAC_KEY');
    } finally {
        $cleanupResetSetupFixture($repository, $envLocalFilename);
    }
});

test('確認をキャンセルすると何も変更しない', function () use ($cleanupResetSetupFixture) {
    /** @var TestCase $this */
    $repository = $this->getContainer()->get(SiteSettingsRepository::class);
    $this->assertInstanceOf(SiteSettingsRepository::class, $repository);

    $envLocalFilename = sys_get_temp_dir() . '/sf-legacy-reset-setup-' . bin2hex(random_bytes(8)) . '/.env.local';

    $repository->setTitle('セットアップ済み掲示板');

    try {
        $tester = new CommandTester(new ResetSetupCommand($repository, new EnvLocalWriter($envLocalFilename)));
        $tester->setInputs(['no']);
        $status = $tester->execute([]);

        expect($status)->toBe(Command::SUCCESS);
        expect($tester->getDisplay())->toContain('初期化を中止しました');
        expect($repository->title())->toBe('セットアップ済み掲示板');
    } finally {
        $cleanupResetSetupFixture($repository, $envLocalFilename);
    }
});
