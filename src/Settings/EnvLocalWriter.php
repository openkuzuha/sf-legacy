<?php

namespace App\Settings;

use RuntimeException;

final class EnvLocalWriter
{
    public function __construct(private readonly string $filename)
    {
    }

    /** @param array<string, string> $values */
    public function upsert(array $values): void
    {
        $this->withLock(fn () => $this->writeValues($values));
    }

    /** @param list<string> $keys */
    public function remove(array $keys): void
    {
        if (!is_file($this->filename)) {
            return;
        }

        $this->withLock(fn () => $this->removeKeys($keys));
    }

    private function withLock(callable $fn): void
    {
        $directory = dirname($this->filename);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('環境設定ファイルの保存先 "%s" を作成できません。', $directory));
        }

        $handle = fopen($this->filename . '.lock', 'c');
        if ($handle === false) {
            throw new RuntimeException('環境設定ファイルをロックできません。');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('環境設定ファイルをロックできません。');
            }
            $fn();
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    /** @param array<string, string> $values */
    private function writeValues(array $values): void
    {
        $lines = is_file($this->filename) ? file($this->filename, FILE_IGNORE_NEW_LINES) : false;
        $lines = $lines === false ? [] : $lines;

        $remaining = $values;
        foreach ($lines as $index => $line) {
            foreach ($remaining as $key => $value) {
                if (preg_match('/^' . preg_quote($key, '/') . '=/', $line) === 1) {
                    $lines[$index] = $this->formatLine($key, $value);
                    unset($remaining[$key]);
                    break;
                }
            }
        }
        foreach ($remaining as $key => $value) {
            $lines[] = $this->formatLine($key, $value);
        }

        $this->replaceContents($lines);
    }

    /** @param list<string> $keys */
    private function removeKeys(array $keys): void
    {
        $lines = file($this->filename, FILE_IGNORE_NEW_LINES);
        $lines = $lines === false ? [] : $lines;

        $lines = array_values(array_filter($lines, function (string $line) use ($keys): bool {
            foreach ($keys as $key) {
                if (preg_match('/^' . preg_quote($key, '/') . '=/', $line) === 1) {
                    return false;
                }
            }

            return true;
        }));

        $this->replaceContents($lines);
    }

    /** @param list<string> $lines */
    private function replaceContents(array $lines): void
    {
        $contents = $lines === [] ? '' : implode("\n", $lines) . "\n";
        $temporaryFilename = $this->filename . '.tmp.' . bin2hex(random_bytes(8));
        try {
            if (file_put_contents($temporaryFilename, $contents, LOCK_EX) === false) {
                throw new RuntimeException('環境設定の一時ファイルを書き込めません。');
            }
            chmod($temporaryFilename, 0600);
            if (!rename($temporaryFilename, $this->filename)) {
                throw new RuntimeException('環境設定ファイルを置き換えられません。');
            }
        } finally {
            if (is_file($temporaryFilename)) {
                unlink($temporaryFilename);
            }
        }
    }

    private function formatLine(string $key, string $value): string
    {
        if (str_contains($value, "'") || str_contains($value, "\n")) {
            throw new RuntimeException('環境設定ファイルへ書き込む値にシングルクォートや改行を含めることはできません。');
        }

        return $key . "='" . $value . "'";
    }
}
