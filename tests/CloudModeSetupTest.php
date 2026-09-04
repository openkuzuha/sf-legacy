<?php

use App\Settings\CloudModeSetup;
use App\Settings\EnvLocalWriter;
use Symfony\Component\Dotenv\Dotenv;

$parseEnvLocalFixture = function (string $filename): array {
    $contents = file_get_contents($filename);
    if (!is_string($contents)) {
        throw new LogicException('環境設定ファイルを読み込めませんでした。');
    }

    return (new Dotenv())->parse($contents, '.env.local');
};

// このプロセスの実環境変数(getenv)を一時的に書き換える。
// .env/.env.local経由の値はDotenvがputenv()しないためgetenv()には現れず、
// ここでの上書きはDockerやホスティング先が渡す「本物の」環境変数だけを模擬する。
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

$cleanupCloudModeSetupFixture = function (string $filename): void {
    if (is_file($filename)) {
        unlink($filename);
    }
    if (is_file($filename . '.lock')) {
        unlink($filename . '.lock');
    }
    if (is_dir(dirname($filename))) {
        @rmdir(dirname($filename));
    }
};

test('実環境変数でCLOUD_MODEが指定されている場合は決定済みとして扱い、変更を拒否する', function () use ($withRealEnv) {
    $withRealEnv('CLOUD_MODE', '1', function () {
        $filename = sys_get_temp_dir() . '/sf-legacy-cloud-mode-' . bin2hex(random_bytes(8)) . '/.env.local';
        $setup = new CloudModeSetup(true, new EnvLocalWriter($filename));

        expect($setup->isFixedByEnvironment())->toBeTrue();
        expect($setup->isDecided())->toBeTrue();
        expect(fn () => $setup->decide(false))->toThrow(RuntimeException::class);
    });
});

test('実環境変数も.env.localも無い場合は未決定として扱い、決定するとファイルへ書き込む', function () use (
    $withRealEnv,
    $parseEnvLocalFixture,
    $cleanupCloudModeSetupFixture,
) {
    $withRealEnv('CLOUD_MODE', null, function () use ($parseEnvLocalFixture, $cleanupCloudModeSetupFixture) {
        $filename = sys_get_temp_dir() . '/sf-legacy-cloud-mode-' . bin2hex(random_bytes(8)) . '/.env.local';
        $setup = new CloudModeSetup(false, new EnvLocalWriter($filename));

        try {
            expect($setup->isFixedByEnvironment())->toBeFalse();
            expect($setup->isDecided())->toBeFalse();

            $setup->decide(true);

            expect($setup->isDecided())->toBeTrue();
            $environment = $parseEnvLocalFixture($filename);
            expect($environment['CLOUD_MODE'])->toBe('1');
        } finally {
            $cleanupCloudModeSetupFixture($filename);
        }
    });
});

test('.env.localにすでにCLOUD_MODEがある場合は決定済みとして扱い、再決定を拒否する', function () use (
    $withRealEnv,
    $cleanupCloudModeSetupFixture,
) {
    $withRealEnv('CLOUD_MODE', null, function () use ($cleanupCloudModeSetupFixture) {
        $filename = sys_get_temp_dir() . '/sf-legacy-cloud-mode-' . bin2hex(random_bytes(8)) . '/.env.local';
        mkdir(dirname($filename), 0775, true);
        file_put_contents($filename, "CLOUD_MODE='0'\n");
        $setup = new CloudModeSetup(false, new EnvLocalWriter($filename));

        try {
            expect($setup->isFixedByEnvironment())->toBeFalse();
            expect($setup->isDecided())->toBeTrue();
            expect(fn () => $setup->decide(true))->toThrow(RuntimeException::class);
        } finally {
            $cleanupCloudModeSetupFixture($filename);
        }
    });
});
