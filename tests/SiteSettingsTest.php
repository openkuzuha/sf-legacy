<?php

use App\Settings\FileSiteSettingsRepository;
use App\Settings\SiteSettings;
use App\Settings\SiteSettingsRepositoryFactory;
use App\Settings\ValkeySiteSettingsRepository;
use Psr\Log\NullLogger;

test('サイトタイトルをJSONへ保存してリセットする', function () {
    $filename = sys_get_temp_dir() . '/sf-legacy-settings-' . bin2hex(random_bytes(8)) . '/settings.json';
    $repository = new FileSiteSettingsRepository($filename);

    try {
        expect($repository->title())->toBeNull();
        $repository->setTitle('変更後タイトル');
        expect((new FileSiteSettingsRepository($filename))->title())->toBe('変更後タイトル');

        $contents = file_get_contents($filename);
        expect($contents)->toBeString();
        expect(json_decode(is_string($contents) ? $contents : '', true))->toBe(['title' => '変更後タイトル']);

        $repository->resetTitle();
        expect($repository->title())->toBeNull();
    } finally {
        if (is_file($filename)) {
            unlink($filename);
        }
        if (is_file($filename . '.lock')) {
            unlink($filename . '.lock');
        }
        $directory = dirname($filename);
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
});

test('保存値がなければAPP_TITLE相当の初期値を返す', function () {
    $repository = new class implements \App\Settings\SiteSettingsRepository {
        public function title(): ?string
        {
            return null;
        }

        public function setTitle(string $title): void
        {
        }

        public function resetTitle(): void
        {
        }
    };
    $settings = new SiteSettings($repository, new NullLogger(), '初期タイトル');

    expect($settings->title())->toBe('初期タイトル');
});

test('保存先を読み込めない場合も初期タイトルを返す', function () {
    $repository = new class implements \App\Settings\SiteSettingsRepository {
        public function title(): ?string
        {
            throw new RuntimeException('読み込みエラー');
        }

        public function setTitle(string $title): void
        {
        }

        public function resetTitle(): void
        {
        }
    };
    $settings = new SiteSettings($repository, new NullLogger(), '初期タイトル');

    expect($settings->title())->toBe('初期タイトル');
});

test('サイトタイトルの空文字と文字数超過を拒否する', function (string $title, string $message) {
    $filename = sys_get_temp_dir() . '/unused-site-settings-' . bin2hex(random_bytes(8)) . '.json';
    $settings = new SiteSettings(new FileSiteSettingsRepository($filename), new NullLogger(), '初期タイトル');

    expect(fn () => $settings->setTitle($title))->toThrow(InvalidArgumentException::class, $message);
})->with([
    '空文字' => ['  ', 'サイトタイトルを入力してください。'],
    '101文字' => [str_repeat('あ', 101), 'サイトタイトルは100文字以内で入力してください。'],
]);

test('CLOUD_MODEに応じてサイト設定の保存先を選ぶ', function () {
    $factory = new SiteSettingsRepositoryFactory();

    expect($factory->create(false, 'redis://localhost', '/tmp/settings.json'))
        ->toBeInstanceOf(FileSiteSettingsRepository::class);
    expect($factory->create(true, 'redis://localhost', '/tmp/settings.json'))
        ->toBeInstanceOf(ValkeySiteSettingsRepository::class);
});
