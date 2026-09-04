<?php

use App\Command\DisableMaintenanceCommand;
use App\Command\EnableMaintenanceCommand;
use App\Settings\SiteSettings;
use App\Tests\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

test('bbs:maintenance:enableは表示文と終了予定日時を指定して有効化する', function () {
    /** @var TestCase $this */
    $settings = $this->getContainer()->get(SiteSettings::class);
    $this->assertInstanceOf(SiteSettings::class, $settings);
    $settings->resetOperationStatus();

    try {
        $tester = new CommandTester(new EnableMaintenanceCommand($settings));
        $status = $tester->execute([
            'message' => 'ただいま更新作業中です。',
            '--ends-at' => '2030-01-02T03:04',
        ]);

        expect($status)->toBe(Command::SUCCESS);
        expect($settings->maintenanceEnabled())->toBeTrue();
        expect($settings->maintenanceMessage())->toBe('ただいま更新作業中です。');
        expect($settings->maintenanceEndsAt()?->format('Y-m-d\TH:i'))->toBe('2030-01-02T03:04');
        // 投稿受付の状態はコマンドの対象外なので、既定値のまま変わらない。
        expect($settings->postingEnabled())->toBeTrue();
    } finally {
        $settings->resetOperationStatus();
    }
});

test('bbs:maintenance:disableは表示文・終了予定日時・投稿受付をそのまま残して無効化する', function () {
    /** @var TestCase $this */
    $settings = $this->getContainer()->get(SiteSettings::class);
    $this->assertInstanceOf(SiteSettings::class, $settings);
    $settings->resetOperationStatus();

    try {
        $enable = new CommandTester(new EnableMaintenanceCommand($settings));
        $enable->execute([
            'message' => 'ただいま更新作業中です。',
            '--ends-at' => '2030-01-02T03:04',
        ]);

        $disable = new CommandTester(new DisableMaintenanceCommand($settings));
        $status = $disable->execute([]);

        expect($status)->toBe(Command::SUCCESS);
        expect($settings->maintenanceEnabled())->toBeFalse();
        expect($settings->maintenanceMessage())->toBe('ただいま更新作業中です。');
        expect($settings->maintenanceEndsAt()?->format('Y-m-d\TH:i'))->toBe('2030-01-02T03:04');
    } finally {
        $settings->resetOperationStatus();
    }
});

test('メッセージとends-atを省略すると現在の設定値を引き継ぐ', function () {
    /** @var TestCase $this */
    $settings = $this->getContainer()->get(SiteSettings::class);
    $this->assertInstanceOf(SiteSettings::class, $settings);
    $settings->resetOperationStatus();

    try {
        $enable = new CommandTester(new EnableMaintenanceCommand($settings));
        $enable->execute([
            'message' => '定期メンテナンスのお知らせです。',
            '--ends-at' => '2030-06-01T00:00',
        ]);
        (new CommandTester(new DisableMaintenanceCommand($settings)))->execute([]);

        // 引数なしで再度有効化しても、前回の表示文・終了予定日時が引き継がれる。
        $status = $enable->execute([]);

        expect($status)->toBe(Command::SUCCESS);
        expect($settings->maintenanceEnabled())->toBeTrue();
        expect($settings->maintenanceMessage())->toBe('定期メンテナンスのお知らせです。');
        expect($settings->maintenanceEndsAt()?->format('Y-m-d\TH:i'))->toBe('2030-06-01T00:00');
    } finally {
        $settings->resetOperationStatus();
    }
});

test('投稿受付が無効な状態でメンテナンスを切り替えても投稿受付の状態は変わらない', function () {
    /** @var TestCase $this */
    $settings = $this->getContainer()->get(SiteSettings::class);
    $this->assertInstanceOf(SiteSettings::class, $settings);
    $settings->resetOperationStatus();
    $settings->setOperationStatus(false, false, $settings->maintenanceMessage(), '');

    try {
        (new CommandTester(new EnableMaintenanceCommand($settings)))->execute(['message' => 'メンテ中']);
        expect($settings->postingEnabled())->toBeFalse();

        (new CommandTester(new DisableMaintenanceCommand($settings)))->execute([]);
        expect($settings->postingEnabled())->toBeFalse();
    } finally {
        $settings->resetOperationStatus();
    }
});

test('不正なends-atはエラーになりメンテナンス状態を変更しない', function () {
    /** @var TestCase $this */
    $settings = $this->getContainer()->get(SiteSettings::class);
    $this->assertInstanceOf(SiteSettings::class, $settings);
    $settings->resetOperationStatus();

    try {
        $tester = new CommandTester(new EnableMaintenanceCommand($settings));
        $status = $tester->execute([
            'message' => 'メンテ中',
            '--ends-at' => 'not-a-datetime',
        ]);

        expect($status)->toBe(Command::FAILURE);
        expect($tester->getDisplay())->toContain('メンテナンス終了予定日時を正しく入力してください。');
        expect($settings->maintenanceEnabled())->toBeFalse();
    } finally {
        $settings->resetOperationStatus();
    }
});
