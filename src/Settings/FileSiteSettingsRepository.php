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
        if (!is_file($this->filename)) {
            return null;
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
        if (!is_array($settings) || !isset($settings['title']) || !is_string($settings['title'])) {
            throw new RuntimeException('サイト設定ファイルに有効なタイトルがありません。');
        }

        return $settings['title'];
    }

    public function setTitle(string $title): void
    {
        $this->withExclusiveLock(function () use ($title): void {
            try {
                $contents = json_encode(
                    ['title' => $title],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                );
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
        });
    }

    public function resetTitle(): void
    {
        $this->withExclusiveLock(function (): void {
            if (is_file($this->filename) && !unlink($this->filename)) {
                throw new RuntimeException('サイト設定ファイルを削除できません。');
            }
        });
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
