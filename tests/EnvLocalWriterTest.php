<?php

use App\Settings\EnvLocalWriter;
use Symfony\Component\Dotenv\Dotenv;

$parseEnvLocalFixture = function (string $filename): array {
    $contents = file_get_contents($filename);
    if (!is_string($contents)) {
        throw new LogicException('環境設定ファイルを読み込めませんでした。');
    }

    return (new Dotenv())->parse($contents, '.env.local');
};

$cleanupEnvLocalWriterFixture = function (string $filename): void {
    if (is_file($filename)) {
        unlink($filename);
    }
    if (is_file($filename . '.lock')) {
        unlink($filename . '.lock');
    }
    if (is_dir(dirname($filename))) {
        rmdir(dirname($filename));
    }
};

test('存在しないファイルへ値を新規作成する', function () use ($parseEnvLocalFixture, $cleanupEnvLocalWriterFixture) {
    $filename = sys_get_temp_dir() . '/sf-legacy-env-local-' . bin2hex(random_bytes(8)) . '/.env.local';
    $writer = new EnvLocalWriter($filename);

    try {
        $writer->upsert(['APP_SECRET' => 'generated-secret-value']);

        expect(is_file($filename))->toBeTrue();
        $environment = $parseEnvLocalFixture($filename);
        expect($environment)->toHaveKey('APP_SECRET')
            ->and($environment['APP_SECRET'])->toBe('generated-secret-value');
        expect(fileperms($filename) & 0777)->toBe(0600);
    } finally {
        $cleanupEnvLocalWriterFixture($filename);
    }
});

test('既存のキーを他の行を壊さずに置き換える', function () use ($parseEnvLocalFixture, $cleanupEnvLocalWriterFixture) {
    $filename = sys_get_temp_dir() . '/sf-legacy-env-local-' . bin2hex(random_bytes(8)) . '/.env.local';
    mkdir(dirname($filename), 0775, true);
    file_put_contents($filename, "ADMIN_PASSWORD_HASH='old-hash'\nAPP_SECRET='old-secret'\n");
    $writer = new EnvLocalWriter($filename);

    try {
        $writer->upsert(['APP_SECRET' => 'new-secret', 'AUDIT_HMAC_KEY' => 'new-audit-key']);

        $environment = $parseEnvLocalFixture($filename);
        expect($environment['ADMIN_PASSWORD_HASH'])->toBe('old-hash')
            ->and($environment['APP_SECRET'])->toBe('new-secret')
            ->and($environment['AUDIT_HMAC_KEY'])->toBe('new-audit-key');
    } finally {
        $cleanupEnvLocalWriterFixture($filename);
    }
});

test('指定したキーの行だけを削除し他の行は残す', function () use ($parseEnvLocalFixture, $cleanupEnvLocalWriterFixture) {
    $filename = sys_get_temp_dir() . '/sf-legacy-env-local-' . bin2hex(random_bytes(8)) . '/.env.local';
    mkdir(dirname($filename), 0775, true);
    $contents = "ADMIN_PASSWORD_HASH='keep-me'\nAPP_SECRET='remove-me'\nAUDIT_HMAC_KEY='remove-me-too'\n";
    file_put_contents($filename, $contents);
    $writer = new EnvLocalWriter($filename);

    try {
        $writer->remove(['APP_SECRET', 'AUDIT_HMAC_KEY']);

        $environment = $parseEnvLocalFixture($filename);
        expect($environment)->toHaveKey('ADMIN_PASSWORD_HASH')
            ->and($environment)->not->toHaveKey('APP_SECRET')
            ->and($environment)->not->toHaveKey('AUDIT_HMAC_KEY');
    } finally {
        $cleanupEnvLocalWriterFixture($filename);
    }
});

test('ファイルが存在しない場合は何もしない', function () {
    $filename = sys_get_temp_dir() . '/sf-legacy-env-local-' . bin2hex(random_bytes(8)) . '/.env.local';
    $writer = new EnvLocalWriter($filename);

    $writer->remove(['APP_SECRET']);

    expect(is_file($filename))->toBeFalse();
});

test('シングルクォートや改行を含む値は拒否する', function (string $value) use ($cleanupEnvLocalWriterFixture) {
    $filename = sys_get_temp_dir() . '/sf-legacy-env-local-' . bin2hex(random_bytes(8)) . '/.env.local';
    $writer = new EnvLocalWriter($filename);

    try {
        expect(fn () => $writer->upsert(['APP_SECRET' => $value]))->toThrow(RuntimeException::class);
    } finally {
        $cleanupEnvLocalWriterFixture($filename);
    }
})->with([
    "シングルクォート" => ["it's-unsafe"],
    "改行" => ["line1\nline2"],
]);
