<?php

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
    $same = $identity->tokens('2001:db8::1', 'Browser 1', $august);

    expect($identity->isConfigured())->toBeTrue()
        ->and($same)->toBe($identity->tokens('2001:0db8:0:0:0:0:0:1', 'Browser 1', $august))
        ->and($same)->not->toBe($identity->tokens('2001:db8::2', 'Browser 1', $august))
        ->and($same)->not->toBe($identity->tokens('2001:db8::1', 'Browser 2', $august))
        ->and($same)->not->toBe($identity->tokens('2001:db8::1', 'Browser 1', $august->modify('+1 month')));
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
        expect($record)->toHaveKeys(['network_token', 'client_token', 'actor_token']);
        expect($record)->not->toHaveKey('ip_address');
        expect($record)->not->toHaveKey('user_agent');
        $settings->setAuditSettings('raw', 30);
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
