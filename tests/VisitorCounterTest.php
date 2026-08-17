<?php

use App\Visitor\VisitorCounter;
use App\Settings\FileSiteSettingsRepository;
use App\Settings\SiteSettings;
use Psr\Log\NullLogger;

test('指定秒数以内にアクセスしたIPごとの参加者数を返す', function () {
    $filename = sys_get_temp_dir() . '/sf-legacy-visitors-' . bin2hex(random_bytes(8)) . '.json';
    $settingsFilename = $filename . '.settings';
    $settings = new SiteSettings(
        new FileSiteSettingsRepository($settingsFilename),
        new NullLogger(),
        'テスト',
        500,
        defaultVisitorActiveSeconds: 300,
    );
    $counter = new VisitorCounter($filename, $settings);

    try {
        expect($counter->count('192.0.2.1', 1_000))->toBe(1)
            ->and($counter->count('192.0.2.1', 1_100))->toBe(1)
            ->and($counter->count('192.0.2.2', 1_200))->toBe(2)
            ->and($counter->count('192.0.2.3', 1_401))->toBe(2);

        $contents = file_get_contents($filename);
        expect($contents)->toBeString()
            ->and($contents)->not->toContain('192.0.2.');
    } finally {
        if (is_file($filename)) {
            unlink($filename);
        }
        if (is_file($settingsFilename)) {
            unlink($settingsFilename);
        }
        if (is_file($settingsFilename . '.lock')) {
            unlink($settingsFilename . '.lock');
        }
    }
});
