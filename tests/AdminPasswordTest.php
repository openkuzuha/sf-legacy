<?php

use App\Settings\AdminPassword;
use App\Settings\FileSiteSettingsRepository;
use Psr\Log\NullLogger;

test('管理用パスワードをハッシュで保存して環境変数より優先する', function () {
    $filename = sys_get_temp_dir() . '/sf-legacy-admin-password-' . bin2hex(random_bytes(8)) . '/settings.json';
    $repository = new FileSiteSettingsRepository($filename);
    $defaultHash = password_hash('environment-password', PASSWORD_DEFAULT);
    expect($defaultHash)->toBeString();
    $password = new AdminPassword($repository, new NullLogger(), $defaultHash);

    try {
        $repository->setTitle('保存済みタイトル');
        expect($password->verify('environment-password'))->toBeTrue();
        $oldFingerprint = $password->fingerprint();
        $password->change('environment-password', 'new-secure-password', 'new-secure-password');

        expect($password->verify('environment-password'))->toBeFalse()
            ->and($password->verify('new-secure-password'))->toBeTrue()
            ->and($password->fingerprint())->not->toBe($oldFingerprint);
        $contents = file_get_contents($filename);
        expect($contents)->toBeString()
            ->and($contents)->not->toContain('new-secure-password')
            ->and($repository->title())->toBe('保存済みタイトル');
        $repository->resetTitle();
        expect($password->verify('new-secure-password'))->toBeTrue();
    } finally {
        if (is_file($filename)) {
            unlink($filename);
        }
        if (is_file($filename . '.lock')) {
            unlink($filename . '.lock');
        }
        if (is_dir(dirname($filename))) {
            rmdir(dirname($filename));
        }
    }
});

test('未設定時にsetInitialでパスワードを保存し、二重設定を拒否する', function () {
    $filename = sys_get_temp_dir() . '/sf-legacy-admin-password-initial-' . bin2hex(random_bytes(8)) . '/settings.json';
    $repository = new FileSiteSettingsRepository($filename);
    $password = new AdminPassword($repository, new NullLogger(), '');

    try {
        expect($password->isConfigured())->toBeFalse();
        $password->setInitial('initial-secure-password', 'initial-secure-password');
        expect($password->isConfigured())->toBeTrue()
            ->and($password->verify('initial-secure-password'))->toBeTrue();

        expect(fn () => $password->setInitial('another-password', 'another-password'))
            ->toThrow(RuntimeException::class, '管理用パスワードはすでに設定されています。');
    } finally {
        if (is_file($filename)) {
            unlink($filename);
        }
        if (is_file($filename . '.lock')) {
            unlink($filename . '.lock');
        }
        if (is_dir(dirname($filename))) {
            rmdir(dirname($filename));
        }
    }
});

test('setInitialの不正入力を拒否する', function (
    string $password,
    string $confirmation,
    string $message,
) {
    $adminPassword = new AdminPassword(
        new FileSiteSettingsRepository(
            sys_get_temp_dir() . '/unused-admin-password-initial-' . bin2hex(random_bytes(8)) . '.json',
        ),
        new NullLogger(),
        '',
    );

    expect(fn () => $adminPassword->setInitial($password, $confirmation))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    '確認が違う' => ['new-secure-password', 'another-password', 'パスワードと確認入力が一致しません。'],
    '短すぎる' => ['short', 'short', 'パスワードは12文字以上で入力してください。'],
]);

test('管理用パスワード変更の不正入力を拒否する', function (
    string $current,
    string $new,
    string $confirmation,
    string $message,
) {
    $hash = password_hash('current-password', PASSWORD_DEFAULT);
    expect($hash)->toBeString();
    $password = new AdminPassword(
        new FileSiteSettingsRepository(
            sys_get_temp_dir() . '/unused-admin-password-' . bin2hex(random_bytes(8)) . '.json',
        ),
        new NullLogger(),
        $hash,
    );

    expect(fn () => $password->change($current, $new, $confirmation))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    '現在値が違う' => ['wrong-password', 'new-secure-password', 'new-secure-password', '現在のパスワードが正しくありません。'],
    '確認が違う' => ['current-password', 'new-secure-password', 'another-password', '新しいパスワードと確認入力が一致しません。'],
    '短すぎる' => ['current-password', 'short', 'short', '新しいパスワードは12文字以上で入力してください。'],
]);
