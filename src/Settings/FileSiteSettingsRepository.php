<?php

namespace App\Settings;

use JsonException;
use RuntimeException;

final class FileSiteSettingsRepository implements SiteSettingsRepository
{
    public function __construct(private readonly string $filename)
    {
    }

    public function title(): ?string
    {
        $settings = $this->readSettings();
        $title = $settings['title'] ?? null;

        return is_string($title) ? $title : null;
    }

    public function setTitle(string $title): void
    {
        $this->withExclusiveLock(function () use ($title): void {
            $settings = $this->readSettings();
            $settings['title'] = $title;
            $this->writeSettings($settings);
        });
    }

    public function resetTitle(): void
    {
        $this->withExclusiveLock(function (): void {
            $settings = $this->readSettings();
            unset($settings['title']);
            $this->writeSettings($settings);
        });
    }

    public function centralPostLimit(): ?int
    {
        $limit = $this->readSettings()['central_post_limit'] ?? null;

        return is_int($limit) ? $limit : null;
    }

    public function setCentralPostLimit(int $limit): void
    {
        $this->withExclusiveLock(function () use ($limit): void {
            $settings = $this->readSettings();
            $settings['central_post_limit'] = $limit;
            $this->writeSettings($settings);
        });
    }

    public function resetCentralPostLimit(): void
    {
        $this->withExclusiveLock(function (): void {
            $settings = $this->readSettings();
            unset($settings['central_post_limit']);
            $this->writeSettings($settings);
        });
    }

    public function defaultDisplayCount(): ?int
    {
        $count = $this->readSettings()['default_display_count'] ?? null;

        return is_int($count) ? $count : null;
    }

    public function setDefaultDisplayCount(int $count): void
    {
        $this->withExclusiveLock(function () use ($count): void {
            $settings = $this->readSettings();
            $settings['default_display_count'] = $count;
            $this->writeSettings($settings);
        });
    }

    public function resetDefaultDisplayCount(): void
    {
        $this->withExclusiveLock(function (): void {
            $settings = $this->readSettings();
            unset($settings['default_display_count']);
            $this->writeSettings($settings);
        });
    }

    public function maxMessageLines(): ?int
    {
        $lines = $this->readSettings()['max_message_lines'] ?? null;

        return is_int($lines) ? $lines : null;
    }

    public function setMaxMessageLines(int $lines): void
    {
        $this->setIntegerSetting('max_message_lines', $lines);
    }

    public function resetMaxMessageLines(): void
    {
        $this->resetSetting('max_message_lines');
    }

    public function maxLineChars(): ?int
    {
        $chars = $this->readSettings()['max_line_chars'] ?? null;

        return is_int($chars) ? $chars : null;
    }

    public function setMaxLineChars(int $chars): void
    {
        $this->setIntegerSetting('max_line_chars', $chars);
    }

    public function resetMaxLineChars(): void
    {
        $this->resetSetting('max_line_chars');
    }

    public function maxMessageChars(): ?int
    {
        $chars = $this->readSettings()['max_message_chars'] ?? null;

        return is_int($chars) ? $chars : null;
    }

    public function setMaxMessageChars(int $chars): void
    {
        $this->setIntegerSetting('max_message_chars', $chars);
    }

    public function resetMaxMessageChars(): void
    {
        $this->resetSetting('max_message_chars');
    }

    public function visitorActiveSeconds(): ?int
    {
        $seconds = $this->readSettings()['visitor_active_seconds'] ?? null;

        return is_int($seconds) ? $seconds : null;
    }

    public function setVisitorActiveSeconds(int $seconds): void
    {
        $this->setIntegerSetting('visitor_active_seconds', $seconds);
    }

    public function resetVisitorActiveSeconds(): void
    {
        $this->resetSetting('visitor_active_seconds');
    }

    public function serviceStartedAt(): ?string
    {
        $date = $this->readSettings()['service_started_at'] ?? null;

        return is_string($date) ? $date : null;
    }

    public function setServiceStartedAt(string $date): void
    {
        $this->withExclusiveLock(function () use ($date): void {
            $settings = $this->readSettings();
            $settings['service_started_at'] = $date;
            $this->writeSettings($settings);
        });
    }

    public function resetServiceStartedAt(): void
    {
        $this->resetSetting('service_started_at');
    }

    public function adminName(): ?string
    {
        $name = $this->readSettings()['admin_name'] ?? null;

        return is_string($name) ? $name : null;
    }

    public function setAdminName(string $name): void
    {
        $this->setStringSetting('admin_name', $name);
    }

    public function resetAdminName(): void
    {
        $this->resetSetting('admin_name');
    }

    public function adminEmail(): ?string
    {
        $email = $this->readSettings()['admin_email'] ?? null;

        return is_string($email) ? $email : null;
    }

    public function setAdminEmail(string $email): void
    {
        $this->setStringSetting('admin_email', $email);
    }

    public function resetAdminEmail(): void
    {
        $this->resetSetting('admin_email');
    }

    public function prohibitedWords(): ?array
    {
        $words = $this->readSettings()['prohibited_words'] ?? null;
        if (!is_array($words)) {
            return null;
        }

        foreach ($words as $word) {
            if (!is_string($word)) {
                return null;
            }
        }

        return array_values($words);
    }

    public function setProhibitedWords(array $words): void
    {
        $this->withExclusiveLock(function () use ($words): void {
            $settings = $this->readSettings();
            $settings['prohibited_words'] = $words;
            $this->writeSettings($settings);
        });
    }

    public function resetProhibitedWords(): void
    {
        $this->resetSetting('prohibited_words');
    }

    public function deniedPostNetworks(): ?array
    {
        $networks = $this->readSettings()['denied_post_networks'] ?? null;
        if (!is_array($networks)) {
            return null;
        }
        foreach ($networks as $network) {
            if (!is_string($network)) {
                return null;
            }
        }

        return array_values($networks);
    }

    public function setDeniedPostNetworks(array $networks): void
    {
        $this->withExclusiveLock(function () use ($networks): void {
            $settings = $this->readSettings();
            $settings['denied_post_networks'] = $networks;
            $this->writeSettings($settings);
        });
    }

    public function resetDeniedPostNetworks(): void
    {
        $this->resetSetting('denied_post_networks');
    }

    public function deniedAccessNetworks(): ?array
    {
        $networks = $this->readSettings()['denied_access_networks'] ?? null;
        if (!is_array($networks)) {
            return null;
        }
        foreach ($networks as $network) {
            if (!is_string($network)) {
                return null;
            }
        }

        return array_values($networks);
    }

    public function setDeniedAccessNetworks(array $networks): void
    {
        $this->withExclusiveLock(function () use ($networks): void {
            $settings = $this->readSettings();
            $settings['denied_access_networks'] = $networks;
            $this->writeSettings($settings);
        });
    }

    public function resetDeniedAccessNetworks(): void
    {
        $this->resetSetting('denied_access_networks');
    }

    public function postingEnabled(): ?bool
    {
        $value = $this->readSettings()['posting_enabled'] ?? null;

        return is_bool($value) ? $value : null;
    }

    public function setPostingEnabled(bool $enabled): void
    {
        $this->setSetting('posting_enabled', $enabled);
    }

    public function resetPostingEnabled(): void
    {
        $this->resetSetting('posting_enabled');
    }

    public function maintenanceEnabled(): ?bool
    {
        $value = $this->readSettings()['maintenance_enabled'] ?? null;

        return is_bool($value) ? $value : null;
    }

    public function setMaintenanceEnabled(bool $enabled): void
    {
        $this->setSetting('maintenance_enabled', $enabled);
    }

    public function resetMaintenanceEnabled(): void
    {
        $this->resetSetting('maintenance_enabled');
    }

    public function maintenanceMessage(): ?string
    {
        $value = $this->readSettings()['maintenance_message'] ?? null;

        return is_string($value) ? $value : null;
    }

    public function setMaintenanceMessage(string $message): void
    {
        $this->setSetting('maintenance_message', $message);
    }

    public function resetMaintenanceMessage(): void
    {
        $this->resetSetting('maintenance_message');
    }

    public function maintenanceEndsAt(): ?string
    {
        $value = $this->readSettings()['maintenance_ends_at'] ?? null;

        return is_string($value) ? $value : null;
    }

    public function setMaintenanceEndsAt(string $endsAt): void
    {
        $this->setSetting('maintenance_ends_at', $endsAt);
    }

    public function resetMaintenanceEndsAt(): void
    {
        $this->resetSetting('maintenance_ends_at');
    }

    public function auditMode(): ?string
    {
        $value = $this->readSettings()['audit_mode'] ?? null;

        return is_string($value) ? $value : null;
    }

    public function setAuditMode(string $mode): void
    {
        $this->setStringSetting('audit_mode', $mode);
    }

    public function resetAuditMode(): void
    {
        $this->resetSetting('audit_mode');
    }

    public function auditRetentionDays(): ?int
    {
        $value = $this->readSettings()['audit_retention_days'] ?? null;

        return is_int($value) ? $value : null;
    }

    public function setAuditRetentionDays(int $days): void
    {
        $this->setIntegerSetting('audit_retention_days', $days);
    }

    public function resetAuditRetentionDays(): void
    {
        $this->resetSetting('audit_retention_days');
    }

    public function undoEnabled(): ?bool
    {
        $enabled = $this->readSettings()['undo_enabled'] ?? null;

        return is_bool($enabled) ? $enabled : null;
    }

    public function setUndoEnabled(bool $enabled): void
    {
        $this->withExclusiveLock(function () use ($enabled): void {
            $settings = $this->readSettings();
            $settings['undo_enabled'] = $enabled;
            $this->writeSettings($settings);
        });
    }

    public function resetUndoEnabled(): void
    {
        $this->resetSetting('undo_enabled');
    }

    public function undoWindowSeconds(): ?int
    {
        $seconds = $this->readSettings()['undo_window_seconds'] ?? null;

        return is_int($seconds) ? $seconds : null;
    }

    public function setUndoWindowSeconds(int $seconds): void
    {
        $this->setIntegerSetting('undo_window_seconds', $seconds);
    }

    public function resetUndoWindowSeconds(): void
    {
        $this->resetSetting('undo_window_seconds');
    }

    public function archiveRetentionDays(): ?int
    {
        $days = $this->readSettings()['archive_retention_days'] ?? null;

        return is_int($days) ? $days : null;
    }

    public function setArchiveRetentionDays(int $days): void
    {
        $this->setIntegerSetting('archive_retention_days', $days);
    }

    public function resetArchiveRetentionDays(): void
    {
        $this->resetSetting('archive_retention_days');
    }

    public function archivePublicDays(): ?int
    {
        $days = $this->readSettings()['archive_public_days'] ?? null;

        return is_int($days) ? $days : null;
    }

    public function setArchivePublicDays(int $days): void
    {
        $this->setIntegerSetting('archive_public_days', $days);
    }

    public function resetArchivePublicDays(): void
    {
        $this->resetSetting('archive_public_days');
    }

    public function adminPasswordHash(): ?string
    {
        $hash = $this->readSettings()['admin_password_hash'] ?? null;

        return is_string($hash) ? $hash : null;
    }

    public function setAdminPasswordHash(string $hash): void
    {
        $this->withExclusiveLock(function () use ($hash): void {
            $settings = $this->readSettings();
            $settings['admin_password_hash'] = $hash;
            $this->writeSettings($settings);
        });
    }

    public function resetAdminPasswordHash(): void
    {
        $this->withExclusiveLock(function (): void {
            $settings = $this->readSettings();
            unset($settings['admin_password_hash']);
            $this->writeSettings($settings);
        });
    }

    /** @return array<string, mixed> */
    private function readSettings(): array
    {
        if (!is_file($this->filename)) {
            return [];
        }
        $contents = file_get_contents($this->filename);
        if ($contents === false) {
            throw new RuntimeException(sprintf('サイト設定ファイル "%s" を読み込めません。', $this->filename));
        }
        try {
            $settings = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('サイト設定ファイルのJSONが不正です。', previous: $exception);
        }
        if (!is_array($settings)) {
            throw new RuntimeException('サイト設定ファイルの形式が不正です。');
        }
        $normalizedSettings = [];
        foreach ($settings as $key => $value) {
            if (!is_string($key)) {
                throw new RuntimeException('サイト設定ファイルのキーが不正です。');
            }
            $normalizedSettings[$key] = $value;
        }

        return $normalizedSettings;
    }

    /** @param array<string, mixed> $settings */
    private function writeSettings(array $settings): void
    {
        try {
            $contents = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('サイト設定をJSONへ変換できません。', previous: $exception);
        }
        $temporaryFilename = $this->filename . '.tmp.' . bin2hex(random_bytes(8));
        try {
            if (file_put_contents($temporaryFilename, $contents . "\n", LOCK_EX) === false) {
                throw new RuntimeException('サイト設定の一時ファイルを書き込めません。');
            }
            if (!rename($temporaryFilename, $this->filename)) {
                throw new RuntimeException('サイト設定ファイルを置き換えられません。');
            }
        } finally {
            if (is_file($temporaryFilename)) {
                unlink($temporaryFilename);
            }
        }
    }

    private function withExclusiveLock(callable $operation): void
    {
        $directory = dirname($this->filename);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('サイト設定の保存先 "%s" を作成できません。', $directory));
        }

        $handle = fopen($this->filename . '.lock', 'c');
        if ($handle === false) {
            throw new RuntimeException('サイト設定のロックファイルを開けません。');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('サイト設定ファイルをロックできません。');
            }
            $operation();
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    private function setStringSetting(string $key, string $value): void
    {
        $this->withExclusiveLock(function () use ($key, $value): void {
            $settings = $this->readSettings();
            $settings[$key] = $value;
            $this->writeSettings($settings);
        });
    }

    private function setIntegerSetting(string $key, int $value): void
    {
        $this->withExclusiveLock(function () use ($key, $value): void {
            $settings = $this->readSettings();
            $settings[$key] = $value;
            $this->writeSettings($settings);
        });
    }

    private function setSetting(string $key, bool|string $value): void
    {
        $this->withExclusiveLock(function () use ($key, $value): void {
            $settings = $this->readSettings();
            $settings[$key] = $value;
            $this->writeSettings($settings);
        });
    }

    private function resetSetting(string $key): void
    {
        $this->withExclusiveLock(function () use ($key): void {
            $settings = $this->readSettings();
            unset($settings[$key]);
            $this->writeSettings($settings);
        });
    }
}
