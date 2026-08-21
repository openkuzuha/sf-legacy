<?php

use App\Audit\AdminActionRecorder;
use App\Audit\AuditIdentity;
use App\Audit\FileAuditLog;
use App\Audit\LegacyAuditScrubber;
use App\Audit\AuditRecorder;
use App\Settings\FileSiteSettingsRepository;
use App\Settings\SiteSettings;
use Psr\Log\NullLogger;

test('監査識別子は同じ月と入力で安定し入力または月が変わると変化する', function () {
    $identity = new AuditIdentity(base64_encode(str_repeat('k', 32)));
    $august = new DateTimeImmutable('2026-08-17T00:00:00Z');

    $networkToken = $identity->networkToken('2001:db8::1', $august);
    expect($identity->isConfigured())->toBeTrue()
        ->and($networkToken)->toBe($identity->networkToken('2001:0db8:0:0:0:0:0:1', $august))
        ->and($networkToken)->not->toBe($identity->networkToken('2001:db8::2', $august))
        ->and($networkToken)->not->toBe($identity->networkToken('2001:db8::1', $august->modify('+1 month')));

    $clientToken = $identity->clientToken('Browser 1', $august);
    expect($clientToken)->toBe($identity->clientToken('Browser 1', $august))
        ->and($clientToken)->not->toBe($identity->clientToken('Browser 2', $august))
        ->and($clientToken)->not->toBe($identity->clientToken('Browser 1', $august->modify('+1 month')));
});

test('ファイル監査ログを非公開権限で保存する', function () {
    $directory = sys_get_temp_dir() . '/bbs-audit-' . bin2hex(random_bytes(8));
    $log = new FileAuditLog($directory, 'Asia/Tokyo');
    $record = [
        'version' => 1,
        'recorded_at' => '2026-08-17T00:00:00Z',
        'request_id' => str_repeat('a', 32),
        'location' => 'main',
        'post_id' => 1,
    ];
    try {
        $log->write($record, 30);
        $filename = $directory . '/2026/08/17.jsonl';
        expect($filename)->toBeFile()
            ->and(fileperms($filename) & 0777)->toBe(0600)
            ->and(json_decode((string) file_get_contents($filename), true, flags: JSON_THROW_ON_ERROR))
            ->toMatchArray($record);
    } finally {
        @unlink($directory . '/2026/08/17.jsonl');
        @rmdir($directory . '/2026/08');
        @rmdir($directory . '/2026');
        @rmdir($directory);
    }
});

test('既存JSONLの生IPとUser-Agentをdry-run後に冪等に消去する', function () {
    $directory = sys_get_temp_dir() . '/bbs-scrub-' . bin2hex(random_bytes(8));
    mkdir($directory, 0700, true);
    $filename = $directory . '/posts.jsonl';
    file_put_contents($filename, json_encode([
        'post_id' => 1,
        'host' => '192.0.2.1',
        'user_agent' => 'Browser',
        'message' => '本文',
    ], JSON_THROW_ON_ERROR) . "\n");
    $scrubber = new LegacyAuditScrubber(
        false,
        $filename,
        $directory . '/archive',
        'redis://localhost',
        'http://localhost',
        'us-east-1',
        'unused',
        'archives',
        'unused',
        'unused',
        true,
    );
    try {
        expect($scrubber->run(false)['affected'])->toBe(1)
            ->and((string) file_get_contents($filename))->toContain('192.0.2.1')
            ->and($scrubber->run(true)['affected'])->toBe(1);
        $clean = (string) file_get_contents($filename);
        expect($clean)->not->toContain('host');
        expect($clean)->not->toContain('user_agent');
        expect($scrubber->run(true)['affected'])->toBe(0);
    } finally {
        @unlink($filename);
        @rmdir($directory);
    }
});

test('仮名化モードでは生IPとUser-Agentを監査ログへ保存しない', function () {
    $directory = sys_get_temp_dir() . '/bbs-recorder-' . bin2hex(random_bytes(8));
    $settingsFile = $directory . '/settings.json';
    mkdir($directory, 0700, true);
    $settings = new SiteSettings(
        new FileSiteSettingsRepository($settingsFile),
        new NullLogger(),
        'Test',
        500,
    );
    $recorder = new AuditRecorder(
        $settings,
        new AuditIdentity(base64_encode(str_repeat('k', 32))),
        new FileAuditLog($directory . '/audit', 'Asia/Tokyo'),
        new NullLogger(),
    );
    try {
        $recorder->record(str_repeat('a', 32), 'main', 10, '192.0.2.10', 'Test Browser');
        $files = glob($directory . '/audit/*/*/*.jsonl') ?: [];
        expect($files)->toHaveCount(1);
        $record = json_decode((string) file_get_contents($files[0]), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($record)) {
            throw new RuntimeException('監査ログが不正です。');
        }
        expect($record)->toHaveKeys(['network_token', 'client_token']);
        expect($record)->not->toHaveKey('ip_address');
        expect($record)->not->toHaveKey('user_agent');
        $settings->setAuditSettings('raw', 'raw', 30);
        $recorder->record(str_repeat('b', 32), 'main', 11, '192.0.2.11', 'Raw Browser');
        $lines = file($files[0], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            throw new RuntimeException('監査ログを読み込めません。');
        }
        expect($lines)->toHaveCount(2);
        $raw = json_decode($lines[1], true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($raw)) {
            throw new RuntimeException('監査ログが不正です。');
        }
        expect($raw['ip_address'])->toBe('192.0.2.11')->and($raw['user_agent'])->toBe('Raw Browser');
    } finally {
        foreach (glob($directory . '/audit/*/*/*.jsonl') ?: [] as $file) {
            @unlink($file);
        }
        foreach (glob($directory . '/audit/*/*', GLOB_ONLYDIR) ?: [] as $month) {
            @rmdir($month);
        }
        foreach (glob($directory . '/audit/*', GLOB_ONLYDIR) ?: [] as $year) {
            @rmdir($year);
        }
        @rmdir($directory . '/audit');
        @unlink($settingsFile);
        @unlink($settingsFile . '.lock');
        @rmdir($directory);
    }
});

test('AUDIT_HMAC_KEY未設定でも生データ記録は動作し、仮名化記録は動作しない', function () {
    $directory = sys_get_temp_dir() . '/bbs-recorder-nokey-' . bin2hex(random_bytes(8));
    $settingsFile = $directory . '/settings.json';
    mkdir($directory, 0700, true);
    $settings = new SiteSettings(
        new FileSiteSettingsRepository($settingsFile),
        new NullLogger(),
        'Test',
        500,
    );
    $recorder = new AuditRecorder(
        $settings,
        new AuditIdentity(''),
        new FileAuditLog($directory . '/audit', 'Asia/Tokyo'),
        new NullLogger(),
    );
    try {
        $settings->setAuditSettings('pseudonymous', 'pseudonymous', 30);
        $recorder->record(str_repeat('a', 32), 'main', 20, '192.0.2.20', 'No Key Browser');
        expect(glob($directory . '/audit/*/*/*.jsonl') ?: [])->toBeEmpty();

        $settings->setAuditSettings('raw', 'raw', 30);
        $recorder->record(str_repeat('b', 32), 'main', 21, '192.0.2.21', 'No Key Raw Browser');
        $files = glob($directory . '/audit/*/*/*.jsonl') ?: [];
        expect($files)->toHaveCount(1);
        $record = json_decode((string) file_get_contents($files[0]), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($record)) {
            throw new RuntimeException('監査ログが不正です。');
        }
        expect($record['ip_address'])->toBe('192.0.2.21')
            ->and($record['user_agent'])->toBe('No Key Raw Browser')
            ->and($record)->not->toHaveKeys(['network_token', 'client_token']);
    } finally {
        foreach (glob($directory . '/audit/*/*/*.jsonl') ?: [] as $file) {
            @unlink($file);
        }
        foreach (glob($directory . '/audit/*/*', GLOB_ONLYDIR) ?: [] as $month) {
            @rmdir($month);
        }
        foreach (glob($directory . '/audit/*', GLOB_ONLYDIR) ?: [] as $year) {
            @rmdir($year);
        }
        @rmdir($directory . '/audit');
        @unlink($settingsFile);
        @unlink($settingsFile . '.lock');
        @rmdir($directory);
    }
});

test('ファイル監査ログは投稿日から該当日のログを検索してpost_idごとに返す', function () {
    $directory = sys_get_temp_dir() . '/bbs-audit-find-' . bin2hex(random_bytes(8));
    $log = new FileAuditLog($directory, 'Asia/Tokyo');

    try {
        $log->write([
            'version' => 1,
            'recorded_at' => '2026-08-17T00:00:00Z',
            'request_id' => str_repeat('a', 32),
            'location' => 'main',
            'post_id' => 1,
            'network_token' => 'n1',
        ], 30);
        $log->write([
            'version' => 1,
            'recorded_at' => '2026-08-17T00:00:00Z',
            'request_id' => str_repeat('b', 32),
            'location' => 'other',
            'post_id' => 2,
            'network_token' => 'n2',
        ], 30);

        $results = $log->findByPosts('main', [
            ['post_id' => 1, 'posted_at' => '2026-08-17T00:00:00Z'],
            ['post_id' => 2, 'posted_at' => '2026-08-17T00:00:00Z'],
            ['post_id' => 3, 'posted_at' => '2026-08-17T00:00:00Z'],
        ]);

        expect($results)->toHaveCount(1)
            ->and($results[1]['network_token'])->toBe('n1')
            ->and($results)->not->toHaveKey(2)
            ->and($results)->not->toHaveKey(3);
    } finally {
        @unlink($directory . '/2026/08/17.jsonl');
        @rmdir($directory . '/2026/08');
        @rmdir($directory . '/2026');
        @rmdir($directory);
    }
});

test('管理操作監査ログは投稿者監査とは別ディレクトリに削除・投稿の操作を記録する', function () {
    $directory = sys_get_temp_dir() . '/bbs-admin-action-' . bin2hex(random_bytes(8));
    $recorder = new AdminActionRecorder(new FileAuditLog($directory, 'Asia/Tokyo'), new NullLogger());

    try {
        $recorder->record(str_repeat('a', 32), 'post_delete', 10, null);
        $recorder->record(str_repeat('b', 32), 'post_create', 11, 11);

        $files = glob($directory . '/*/*/*.jsonl') ?: [];
        expect($files)->toHaveCount(1);
        $lines = file($files[0], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            throw new RuntimeException('管理操作監査ログを読み込めません。');
        }
        expect($lines)->toHaveCount(2);

        $delete = json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR);
        $create = json_decode($lines[1], true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($delete) || !is_array($create)) {
            throw new RuntimeException('管理操作監査ログが不正です。');
        }
        expect($delete)->toMatchArray(['action' => 'post_delete', 'post_id' => 10, 'thread_id' => null]);
        expect($create)->toMatchArray(['action' => 'post_create', 'post_id' => 11, 'thread_id' => 11]);
    } finally {
        foreach (glob($directory . '/*/*/*.jsonl') ?: [] as $file) {
            @unlink($file);
        }
        foreach (glob($directory . '/*/*', GLOB_ONLYDIR) ?: [] as $month) {
            @rmdir($month);
        }
        foreach (glob($directory . '/*', GLOB_ONLYDIR) ?: [] as $year) {
            @rmdir($year);
        }
        @rmdir($directory);
    }
});
