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

test('マスターログ保存件数をJSONへ保存してリセットする', function () {
    $filename = sys_get_temp_dir() . '/sf-legacy-settings-' . bin2hex(random_bytes(8)) . '/settings.json';
    $repository = new FileSiteSettingsRepository($filename);

    try {
        expect($repository->centralPostLimit())->toBeNull();
        $repository->setCentralPostLimit(250);
        expect((new FileSiteSettingsRepository($filename))->centralPostLimit())->toBe(250);
        $repository->resetCentralPostLimit();
        expect($repository->centralPostLimit())->toBeNull();
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

test('マスターログ保存件数を検証する', function () {
    $filename = sys_get_temp_dir() . '/sf-legacy-limit-settings-' . bin2hex(random_bytes(8)) . '.json';
    $settings = new SiteSettings(new FileSiteSettingsRepository($filename), new NullLogger(), '初期タイトル', 500);

    try {
        expect($settings->centralPostLimit())->toBe(500);
        $settings->setCentralPostLimit(2);
        expect($settings->centralPostLimit())->toBe(2);
        expect(fn () => $settings->setCentralPostLimit(0))
            ->toThrow(InvalidArgumentException::class, 'マスターログ保存件数は1件以上100000件以下で入力してください。');
    } finally {
        if (is_file($filename)) {
            unlink($filename);
        }
        if (is_file($filename . '.lock')) {
            unlink($filename . '.lock');
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

        public function centralPostLimit(): ?int
        {
            return null;
        }
        public function setCentralPostLimit(int $limit): void
        {
        }
        public function resetCentralPostLimit(): void
        {
        }
        public function defaultDisplayCount(): ?int
        {
            return null;
        }
        public function setDefaultDisplayCount(int $count): void
        {
        }
        public function resetDefaultDisplayCount(): void
        {
        }
        public function maxMessageLines(): ?int
        {
            return null;
        }
        public function setMaxMessageLines(int $lines): void
        {
        }
        public function resetMaxMessageLines(): void
        {
        }
        public function maxLineChars(): ?int
        {
            return null;
        }
        public function setMaxLineChars(int $chars): void
        {
        }
        public function resetMaxLineChars(): void
        {
        }
        public function maxMessageChars(): ?int
        {
            return null;
        }
        public function setMaxMessageChars(int $chars): void
        {
        }
        public function resetMaxMessageChars(): void
        {
        }
        public function visitorActiveSeconds(): ?int
        {
            return null;
        }
        public function setVisitorActiveSeconds(int $seconds): void
        {
        }
        public function resetVisitorActiveSeconds(): void
        {
        }
        public function serviceStartedAt(): ?string
        {
            return null;
        }
        public function setServiceStartedAt(string $date): void
        {
        }
        public function resetServiceStartedAt(): void
        {
        }
        public function adminName(): ?string
        {
            return null;
        }
        public function setAdminName(string $name): void
        {
        }
        public function resetAdminName(): void
        {
        }
        public function adminEmail(): ?string
        {
            return null;
        }
        public function setAdminEmail(string $email): void
        {
        }
        public function resetAdminEmail(): void
        {
        }
        public function prohibitedWords(): ?array
        {
            return null;
        }
        public function setProhibitedWords(array $words): void
        {
        }
        public function resetProhibitedWords(): void
        {
        }
        public function deniedPostNetworks(): ?array
        {
            return null;
        }
        public function setDeniedPostNetworks(array $networks): void
        {
        }
        public function resetDeniedPostNetworks(): void
        {
        }
        public function deniedAccessNetworks(): ?array
        {
            return null;
        }
        public function setDeniedAccessNetworks(array $networks): void
        {
        }
        public function resetDeniedAccessNetworks(): void
        {
        }
        public function undoEnabled(): ?bool
        {
            return null;
        }
        public function setUndoEnabled(bool $enabled): void
        {
        }
        public function resetUndoEnabled(): void
        {
        }
        public function undoWindowSeconds(): ?int
        {
            return null;
        }
        public function setUndoWindowSeconds(int $seconds): void
        {
        }
        public function resetUndoWindowSeconds(): void
        {
        }
        public function archiveRetentionDays(): ?int
        {
            return null;
        }
        public function setArchiveRetentionDays(int $days): void
        {
        }
        public function resetArchiveRetentionDays(): void
        {
        }
        public function archivePublicDays(): ?int
        {
            return null;
        }
        public function setArchivePublicDays(int $days): void
        {
        }
        public function resetArchivePublicDays(): void
        {
        }

        public function postingEnabled(): ?bool
        {
            return null;
        }
        public function setPostingEnabled(bool $enabled): void
        {
        }
        public function resetPostingEnabled(): void
        {
        }
        public function maintenanceEnabled(): ?bool
        {
            return null;
        }
        public function setMaintenanceEnabled(bool $enabled): void
        {
        }
        public function resetMaintenanceEnabled(): void
        {
        }
        public function maintenanceMessage(): ?string
        {
            return null;
        }
        public function setMaintenanceMessage(string $message): void
        {
        }
        public function resetMaintenanceMessage(): void
        {
        }
        public function maintenanceEndsAt(): ?string
        {
            return null;
        }
        public function setMaintenanceEndsAt(string $endsAt): void
        {
        }
        public function resetMaintenanceEndsAt(): void
        {
        }
        public function auditMode(): ?string
        {
            return null;
        }
        public function setAuditMode(string $mode): void
        {
        }
        public function resetAuditMode(): void
        {
        }
        public function auditRetentionDays(): ?int
        {
            return null;
        }
        public function setAuditRetentionDays(int $days): void
        {
        }
        public function resetAuditRetentionDays(): void
        {
        }

        public function adminPasswordHash(): ?string
        {
            return null;
        }

        public function setAdminPasswordHash(string $hash): void
        {
        }

        public function resetAdminPasswordHash(): void
        {
        }
    };
    $settings = new SiteSettings($repository, new NullLogger(), '初期タイトル', 500);

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

        public function centralPostLimit(): ?int
        {
            throw new RuntimeException('読み込みエラー');
        }
        public function setCentralPostLimit(int $limit): void
        {
        }
        public function resetCentralPostLimit(): void
        {
        }
        public function defaultDisplayCount(): ?int
        {
            throw new RuntimeException('読み込みエラー');
        }
        public function setDefaultDisplayCount(int $count): void
        {
        }
        public function resetDefaultDisplayCount(): void
        {
        }
        public function maxMessageLines(): ?int
        {
            throw new RuntimeException('読み込みエラー');
        }
        public function setMaxMessageLines(int $lines): void
        {
        }
        public function resetMaxMessageLines(): void
        {
        }
        public function maxLineChars(): ?int
        {
            throw new RuntimeException('読み込みエラー');
        }
        public function setMaxLineChars(int $chars): void
        {
        }
        public function resetMaxLineChars(): void
        {
        }
        public function maxMessageChars(): ?int
        {
            throw new RuntimeException('読み込みエラー');
        }
        public function setMaxMessageChars(int $chars): void
        {
        }
        public function resetMaxMessageChars(): void
        {
        }
        public function visitorActiveSeconds(): ?int
        {
            throw new RuntimeException('読み込みエラー');
        }
        public function setVisitorActiveSeconds(int $seconds): void
        {
        }
        public function resetVisitorActiveSeconds(): void
        {
        }
        public function serviceStartedAt(): ?string
        {
            throw new RuntimeException('読み込みエラー');
        }
        public function setServiceStartedAt(string $date): void
        {
        }
        public function resetServiceStartedAt(): void
        {
        }
        public function adminName(): ?string
        {
            throw new RuntimeException('読み込みエラー');
        }
        public function setAdminName(string $name): void
        {
        }
        public function resetAdminName(): void
        {
        }
        public function adminEmail(): ?string
        {
            throw new RuntimeException('読み込みエラー');
        }
        public function setAdminEmail(string $email): void
        {
        }
        public function resetAdminEmail(): void
        {
        }
        public function prohibitedWords(): ?array
        {
            throw new RuntimeException('読み込みエラー');
        }
        public function setProhibitedWords(array $words): void
        {
        }
        public function resetProhibitedWords(): void
        {
        }
        public function deniedPostNetworks(): ?array
        {
            return null;
        }
        public function setDeniedPostNetworks(array $networks): void
        {
        }
        public function resetDeniedPostNetworks(): void
        {
        }
        public function deniedAccessNetworks(): ?array
        {
            return null;
        }
        public function setDeniedAccessNetworks(array $networks): void
        {
        }
        public function resetDeniedAccessNetworks(): void
        {
        }
        public function undoEnabled(): ?bool
        {
            throw new RuntimeException('読み込みエラー');
        }
        public function setUndoEnabled(bool $enabled): void
        {
        }
        public function resetUndoEnabled(): void
        {
        }
        public function undoWindowSeconds(): ?int
        {
            throw new RuntimeException('読み込みエラー');
        }
        public function setUndoWindowSeconds(int $seconds): void
        {
        }
        public function resetUndoWindowSeconds(): void
        {
        }
        public function archiveRetentionDays(): ?int
        {
            throw new RuntimeException('読み込みエラー');
        }
        public function setArchiveRetentionDays(int $days): void
        {
        }
        public function resetArchiveRetentionDays(): void
        {
        }
        public function archivePublicDays(): ?int
        {
            throw new RuntimeException('読み込みエラー');
        }
        public function setArchivePublicDays(int $days): void
        {
        }
        public function resetArchivePublicDays(): void
        {
        }

        public function postingEnabled(): ?bool
        {
            return null;
        }
        public function setPostingEnabled(bool $enabled): void
        {
        }
        public function resetPostingEnabled(): void
        {
        }
        public function maintenanceEnabled(): ?bool
        {
            return null;
        }
        public function setMaintenanceEnabled(bool $enabled): void
        {
        }
        public function resetMaintenanceEnabled(): void
        {
        }
        public function maintenanceMessage(): ?string
        {
            return null;
        }
        public function setMaintenanceMessage(string $message): void
        {
        }
        public function resetMaintenanceMessage(): void
        {
        }
        public function maintenanceEndsAt(): ?string
        {
            return null;
        }
        public function setMaintenanceEndsAt(string $endsAt): void
        {
        }
        public function resetMaintenanceEndsAt(): void
        {
        }
        public function auditMode(): ?string
        {
            return null;
        }
        public function setAuditMode(string $mode): void
        {
        }
        public function resetAuditMode(): void
        {
        }
        public function auditRetentionDays(): ?int
        {
            return null;
        }
        public function setAuditRetentionDays(int $days): void
        {
        }
        public function resetAuditRetentionDays(): void
        {
        }

        public function adminPasswordHash(): ?string
        {
            return null;
        }

        public function setAdminPasswordHash(string $hash): void
        {
        }

        public function resetAdminPasswordHash(): void
        {
        }
    };
    $settings = new SiteSettings($repository, new NullLogger(), '初期タイトル', 500);

    expect($settings->title())->toBe('初期タイトル');
});

test('サイトタイトルの空文字と文字数超過を拒否する', function (string $title, string $message) {
    $filename = sys_get_temp_dir() . '/unused-site-settings-' . bin2hex(random_bytes(8)) . '.json';
    $settings = new SiteSettings(new FileSiteSettingsRepository($filename), new NullLogger(), '初期タイトル', 500);

    expect(fn () => $settings->setTitle($title))->toThrow(InvalidArgumentException::class, $message);
})->with([
    '空文字' => ['  ', 'サイトタイトルを入力してください。'],
    '101文字' => [str_repeat('あ', 101), 'サイトタイトルは100文字以内で入力してください。'],
]);

test('サービス開始日をJSONへ保存し不正な日付を拒否する', function () {
    $filename = sys_get_temp_dir() . '/service-start-settings-' . bin2hex(random_bytes(8)) . '.json';
    $settings = new SiteSettings(
        new FileSiteSettingsRepository($filename),
        new NullLogger(),
        '初期タイトル',
        500,
        defaultServiceStartedAt: '2026-08-12',
    );

    try {
        expect($settings->serviceStartedAt())->toBe('2026-08-12');
        $settings->setServiceStartedAt('2025-04-01');
        expect($settings->serviceStartedAt())->toBe('2025-04-01')
            ->and($settings->formattedServiceStartedAt())->toBe('2025/04/01');
        expect(fn () => $settings->setServiceStartedAt('2025-02-30'))
            ->toThrow(InvalidArgumentException::class, 'サービス開始日を正しい日付で入力してください。');
        $settings->resetServiceStartedAt();
        expect($settings->serviceStartedAt())->toBe('2026-08-12');
    } finally {
        if (is_file($filename)) {
            unlink($filename);
        }
        if (is_file($filename . '.lock')) {
            unlink($filename . '.lock');
        }
    }
});

test('管理者名とメールアドレスを保存して検証する', function () {
    $filename = sys_get_temp_dir() . '/admin-identity-settings-' . bin2hex(random_bytes(8)) . '.json';
    $settings = new SiteSettings(new FileSiteSettingsRepository($filename), new NullLogger(), '初期タイトル', 500);

    try {
        expect($settings->adminName())->toBe('管理人')
            ->and($settings->adminEmail())->toBe('');
        $settings->setAdminIdentity(' 掲示板管理者 ', ' admin@example.com ');
        expect($settings->adminName())->toBe('掲示板管理者')
            ->and($settings->adminEmail())->toBe('admin@example.com');
        expect(fn () => $settings->setAdminIdentity('', 'admin@example.com'))
            ->toThrow(InvalidArgumentException::class, '管理者名を入力してください。');
        expect(fn () => $settings->setAdminIdentity('管理者', 'invalid-address'))
            ->toThrow(InvalidArgumentException::class, '管理者メールアドレスを正しい形式で入力してください。');
        $settings->resetAdminName();
        $settings->resetAdminEmail();
        expect($settings->adminName())->toBe('管理人')
            ->and($settings->adminEmail())->toBe('');
    } finally {
        if (is_file($filename)) {
            unlink($filename);
        }
        if (is_file($filename . '.lock')) {
            unlink($filename . '.lock');
        }
    }
});

test('投稿禁止ワードを1行1語で保存して正規化する', function () {
    $filename = sys_get_temp_dir() . '/prohibited-word-settings-' . bin2hex(random_bytes(8)) . '.json';
    $settings = new SiteSettings(new FileSiteSettingsRepository($filename), new NullLogger(), '初期タイトル', 500);

    try {
        expect($settings->prohibitedWords())->toBe([]);
        $settings->setProhibitedWordsText(" 禁止語 \r\n\r\n別の語\n禁止語");
        expect($settings->prohibitedWords())->toBe(['禁止語', '別の語'])
            ->and($settings->prohibitedWordsText())->toBe("禁止語\n別の語");
        expect(fn () => $settings->setProhibitedWordsText(str_repeat('あ', 101)))
            ->toThrow(InvalidArgumentException::class, '投稿禁止ワードは1件100文字以内で入力してください。');
        $settings->resetProhibitedWords();
        expect($settings->prohibitedWords())->toBe([]);
    } finally {
        if (is_file($filename)) {
            unlink($filename);
        }
        if (is_file($filename . '.lock')) {
            unlink($filename . '.lock');
        }
    }
});

test('投稿者による削除設定を保存して検証する', function () {
    $filename = sys_get_temp_dir() . '/undo-settings-' . bin2hex(random_bytes(8)) . '.json';
    $settings = new SiteSettings(new FileSiteSettingsRepository($filename), new NullLogger(), '初期タイトル', 500);

    try {
        expect($settings->undoEnabled())->toBeTrue()
            ->and($settings->undoWindowSeconds())->toBe(86400);
        $settings->setUndoSettings(false, 3600);
        expect($settings->undoEnabled())->toBeFalse()
            ->and($settings->undoWindowSeconds())->toBe(3600);
        expect(fn () => $settings->setUndoSettings(true, 0))
            ->toThrow(InvalidArgumentException::class, '投稿の削除可能時間は1秒以上2592000秒以下で入力してください。');
        $settings->resetUndoSettings();
        expect($settings->undoEnabled())->toBeTrue()
            ->and($settings->undoWindowSeconds())->toBe(86400);
    } finally {
        if (is_file($filename)) {
            unlink($filename);
        }
        if (is_file($filename . '.lock')) {
            unlink($filename . '.lock');
        }
    }
});

test('過去ログ保持日数を保存して検証する', function () {
    $filename = sys_get_temp_dir() . '/archive-retention-settings-' . bin2hex(random_bytes(8)) . '.json';
    $settings = new SiteSettings(new FileSiteSettingsRepository($filename), new NullLogger(), '初期タイトル', 500);

    try {
        expect($settings->archiveRetentionDays())->toBe(0);
        $settings->setArchiveRetentionDays(30);
        expect($settings->archiveRetentionDays())->toBe(30);
        expect(fn () => $settings->setArchiveRetentionDays(3651))
            ->toThrow(InvalidArgumentException::class, '過去ログ保持日数は0日以上3650日以下で入力してください。');
        $settings->resetArchiveRetentionDays();
        expect($settings->archiveRetentionDays())->toBe(0);
    } finally {
        if (is_file($filename)) {
            unlink($filename);
        }
        if (is_file($filename . '.lock')) {
            unlink($filename . '.lock');
        }
    }
});

test('過去ログ公開日数を保存して検証する', function () {
    $filename = sys_get_temp_dir() . '/archive-public-settings-' . bin2hex(random_bytes(8)) . '.json';
    $settings = new SiteSettings(new FileSiteSettingsRepository($filename), new NullLogger(), '初期タイトル', 500);

    try {
        expect($settings->archivePublicDays())->toBe(30);
        $settings->setArchivePublicDays(90);
        expect($settings->archivePublicDays())->toBe(90);
        expect(fn () => $settings->setArchivePublicDays(0))
            ->toThrow(InvalidArgumentException::class, '過去ログ公開日数は1日以上3650日以下で入力してください。');
        $settings->resetArchivePublicDays();
        expect($settings->archivePublicDays())->toBe(30);
    } finally {
        if (is_file($filename)) {
            unlink($filename);
        }
        if (is_file($filename . '.lock')) {
            unlink($filename . '.lock');
        }
    }
});

test('投稿者監査モードと保存日数を保存して検証する', function () {
    $filename = sys_get_temp_dir() . '/audit-settings-' . bin2hex(random_bytes(8)) . '.json';
    $settings = new SiteSettings(new FileSiteSettingsRepository($filename), new NullLogger(), '初期タイトル', 500);
    try {
        expect($settings->auditMode())->toBe('pseudonymous')->and($settings->auditRetentionDays())->toBe(30);
        $settings->setAuditSettings('raw', 14);
        expect($settings->auditMode())->toBe('raw')->and($settings->auditRetentionDays())->toBe(14);
        expect(fn () => $settings->setAuditSettings('invalid', 14))->toThrow(InvalidArgumentException::class)
            ->and(fn () => $settings->setAuditSettings('none', 0))->toThrow(InvalidArgumentException::class);
        $settings->resetAuditSettings();
        expect($settings->auditMode())->toBe('pseudonymous')->and($settings->auditRetentionDays())->toBe(30);
    } finally {
        @unlink($filename);
    }
});

test('CLOUD_MODEに応じてサイト設定の保存先を選ぶ', function () {
    $factory = new SiteSettingsRepositoryFactory();

    expect($factory->create(false, 'redis://localhost', '/tmp/settings.json'))
        ->toBeInstanceOf(FileSiteSettingsRepository::class);
    expect($factory->create(true, 'redis://localhost', '/tmp/settings.json'))
        ->toBeInstanceOf(ValkeySiteSettingsRepository::class);
});
