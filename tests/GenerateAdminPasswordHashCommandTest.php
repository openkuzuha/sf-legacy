<?php

use App\Command\GenerateAdminPasswordHashCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Dotenv\Dotenv;

test('管理画面用パスワードのハッシュを生成する', function () {
    $tester = new CommandTester(new GenerateAdminPasswordHashCommand());
    $tester->setInputs(['secret-password', 'secret-password']);

    $status = $tester->execute([]);
    $display = $tester->getDisplay();

    expect($status)->toBe(Command::SUCCESS);
    expect($display)->toContain('ADMIN_PASSWORD_HASH=');
    expect($display)->not->toContain('secret-password');

    $matched = preg_match("/ADMIN_PASSWORD_HASH='[^']+'/", $display, $matches);
    $assignment = $matches[0] ?? null;
    expect($matched)->toBe(1);
    expect($assignment)->toBeString();
    if (!is_string($assignment)) {
        throw new LogicException('生成された環境変数設定を取得できませんでした。');
    }

    $environment = (new Dotenv())->parse($assignment, '.env');
    $hash = $environment['ADMIN_PASSWORD_HASH'] ?? null;
    expect($environment)->toHaveKey('ADMIN_PASSWORD_HASH');
    expect($hash)->toBeString();
    if (!is_string($hash)) {
        throw new LogicException('環境変数からハッシュを取得できませんでした。');
    }
    expect(password_verify('secret-password', $hash))->toBeTrue();
});

test('確認用パスワードが一致しない場合は生成しない', function () {
    $tester = new CommandTester(new GenerateAdminPasswordHashCommand());
    $tester->setInputs(['secret-password', 'different-password']);

    $status = $tester->execute([]);

    expect($status)->toBe(Command::FAILURE);
    expect($tester->getDisplay())->toContain('入力したパスワードが一致しません。');
    expect($tester->getDisplay())->not->toContain('ADMIN_PASSWORD_HASH=');
});
