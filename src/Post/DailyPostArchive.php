<?php

namespace App\Post;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/** @phpstan-import-type PostRecord from PostTypes */
final class DailyPostArchive implements PostArchive
{
    private readonly DateTimeZone $timezone;

    public function __construct(
        private readonly string $directory,
        private readonly PostRecordCodec $codec,
        string $appTimezone,
    ) {
        $this->timezone = new DateTimeZone($appTimezone);
    }

    /** @param PostRecord $record */
    public function put(array $record): bool
    {
        $filename = $this->filename($record['posted_at']);
        $directory = dirname($filename);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('アーカイブディレクトリ "%s" を作成できません。', $directory));
        }

        $handle = fopen($filename, 'c+');
        if ($handle === false) {
            throw new RuntimeException(sprintf('アーカイブファイル "%s" を開けません。', $filename));
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('アーカイブファイルをロックできません。');
            }
            while (($line = fgets($handle)) !== false) {
                $existing = $this->codec->decode($line);
                if (
                    $existing !== null
                    && $existing['location'] === $record['location']
                    && $existing['post_id'] === $record['post_id']
                ) {
                    if ($existing === $record) {
                        return false;
                    }
                    throw new RuntimeException(sprintf('アーカイブ内の投稿ID %d の内容が一致しません。', $record['post_id']));
                }
            }
            if (fseek($handle, 0, SEEK_END) !== 0 || fwrite($handle, $this->codec->encode($record) . "\n") === false) {
                throw new RuntimeException('投稿をアーカイブファイルへ書き込めません。');
            }
            fflush($handle);

            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @param PostRecord $record */
    public function delete(array $record): bool
    {
        $filename = $this->filename($record['posted_at']);
        if (!is_file($filename)) {
            return false;
        }

        $handle = fopen($filename, 'c+');
        if ($handle === false) {
            throw new RuntimeException(sprintf('アーカイブファイル "%s" を開けません。', $filename));
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('アーカイブファイルをロックできません。');
            }
            $kept = [];
            $deleted = false;
            while (($line = fgets($handle)) !== false) {
                $existing = $this->codec->decode($line);
                if (
                    $existing !== null
                    && $existing['location'] === $record['location']
                    && $existing['post_id'] === $record['post_id']
                ) {
                    $deleted = true;
                    continue;
                }
                $kept[] = $line;
            }
            if (!$deleted) {
                return false;
            }
            if (!ftruncate($handle, 0) || !rewind($handle)) {
                throw new RuntimeException('アーカイブから投稿を削除できません。');
            }
            foreach ($kept as $line) {
                if (fwrite($handle, $line) === false) {
                    throw new RuntimeException('アーカイブから投稿を削除できません。');
                }
            }
            fflush($handle);

            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @return list<PostRecord> */
    public function month(string $yearMonth): array
    {
        if (preg_match('/^\d{4}-\d{2}$/D', $yearMonth) !== 1) {
            throw new RuntimeException('年月は YYYY-MM 形式で指定してください。');
        }

        $records = [];
        $filenames = glob(sprintf('%s/%s/*.jsonl', rtrim($this->directory, '/'), str_replace('-', '/', $yearMonth)));
        if ($filenames === false) {
            throw new RuntimeException('日別アーカイブを列挙できません。');
        }
        sort($filenames, SORT_STRING);
        foreach ($filenames as $filename) {
            foreach (file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $record = $this->codec->decode($line);
                if ($record !== null) {
                    $records[] = $record;
                }
            }
        }

        return $records;
    }

    /** @return list<array{date: string, size: int, formatted_size: string, post_count: int}> */
    public function entries(): array
    {
        $filenames = glob(sprintf('%s/*/*/*.jsonl', rtrim($this->directory, '/')));
        if ($filenames === false) {
            throw new RuntimeException('日別アーカイブを列挙できません。');
        }
        sort($filenames, SORT_STRING);

        $entries = [];
        foreach ($filenames as $filename) {
            $relative = substr($filename, strlen(rtrim($this->directory, '/')) + 1);
            if (preg_match('#^(\d{4})/(\d{2})/(\d{2})\.jsonl$#D', $relative, $matches) !== 1) {
                continue;
            }
            $size = filesize($filename);
            if ($size === false) {
                throw new RuntimeException(sprintf('アーカイブファイル "%s" のサイズを取得できません。', $filename));
            }
            $postCount = 0;
            foreach (file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                if ($this->codec->decode($line) !== null) {
                    ++$postCount;
                }
            }
            $entries[] = [
                'date' => sprintf('%s/%s/%s', $matches[1], $matches[2], $matches[3]),
                'size' => $size,
                'formatted_size' => self::formatBytes($size),
                'post_count' => $postCount,
            ];
        }

        return $entries;
    }

    /**
     * @param list<string> $dates
     * @return list<PostRecord>
     */
    public function search(array $dates, string $keyword): array
    {
        $records = [];
        foreach (array_unique($dates) as $date) {
            if (preg_match('#^\d{4}/\d{2}/\d{2}$#D', $date) !== 1) {
                continue;
            }
            $filename = sprintf('%s/%s.jsonl', rtrim($this->directory, '/'), $date);
            if (!is_file($filename)) {
                continue;
            }
            foreach (file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $record = $this->codec->decode($line);
                if ($record === null) {
                    continue;
                }
                $target = implode("\n", [
                    $record['author'],
                    $record['email'],
                    $record['title'],
                    $record['message'],
                ]);
                if ($keyword === '' || mb_stripos($target, $keyword) !== false) {
                    $records[] = $record;
                }
            }
        }
        usort($records, static fn (array $left, array $right): int => $left['posted_at'] <=> $right['posted_at']);

        return $records;
    }

    /** @return int 削除した投稿件数 */
    public function clear(): int
    {
        $filenames = glob(sprintf('%s/*/*/*.jsonl', rtrim($this->directory, '/')));
        if ($filenames === false) {
            throw new RuntimeException('日別アーカイブを列挙できません。');
        }

        $count = 0;
        foreach ($filenames as $filename) {
            foreach (file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                if ($this->codec->decode($line) !== null) {
                    ++$count;
                }
            }
            if (!unlink($filename)) {
                throw new RuntimeException(sprintf('アーカイブファイル "%s" を削除できません。', $filename));
            }
        }

        return $count;
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return sprintf('%d B', $bytes);
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $size = $bytes / 1024;
        foreach ($units as $unit) {
            if ($size < 1024 || $unit === 'TB') {
                return sprintf('%s %s', number_format($size, 1), $unit);
            }
            $size /= 1024;
        }

        return sprintf('%d B', $bytes);
    }

    private function filename(string $postedAt): string
    {
        $date = (new DateTimeImmutable($postedAt))->setTimezone($this->timezone);

        return sprintf('%s/%s.jsonl', rtrim($this->directory, '/'), $date->format('Y/m/d'));
    }
}
