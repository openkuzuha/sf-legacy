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
}
