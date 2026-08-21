<?php

namespace App\Audit;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use RuntimeException;

final class FileAuditLog implements AuditLog
{
    public function __construct(
        private readonly string $directory,
        private readonly string $appTimezone,
    ) {
    }

    public function write(array $record, int $retentionDays): void
    {
        $postedAt = new DateTimeImmutable((string) $record['recorded_at']);
        $filename = sprintf(
            '%s/%s.jsonl',
            rtrim($this->directory, '/'),
            $postedAt->setTimezone(new DateTimeZone($this->appTimezone))->format('Y/m/d'),
        );
        $directory = dirname($filename);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('監査ログディレクトリを作成できません。');
        }
        chmod($directory, 0700);
        try {
            $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        } catch (JsonException $exception) {
            throw new RuntimeException('監査情報をJSONへ変換できません。', previous: $exception);
        }
        $handle = fopen($filename, 'ab');
        if ($handle === false) {
            throw new RuntimeException('監査ログを開けません。');
        }
        try {
            chmod($filename, 0600);
            if (!flock($handle, LOCK_EX) || fwrite($handle, $line) === false) {
                throw new RuntimeException('監査ログへ書き込めません。');
            }
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
        $this->prune($retentionDays);
    }

    public function findByPosts(string $location, array $posts): array
    {
        $wantedPostIds = array_flip(array_column($posts, 'post_id'));
        $dates = [];
        foreach ($posts as $post) {
            try {
                $postedAt = new DateTimeImmutable($post['posted_at']);
            } catch (\Exception) {
                continue;
            }
            $dates[$postedAt->setTimezone(new DateTimeZone($this->appTimezone))->format('Y/m/d')] = true;
        }

        $results = [];
        foreach (array_keys($dates) as $date) {
            $filename = sprintf('%s/%s.jsonl', rtrim($this->directory, '/'), $date);
            $lines = is_file($filename) ? file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : false;
            if ($lines === false) {
                continue;
            }
            foreach ($lines as $line) {
                try {
                    $record = $this->sanitizeRecord(json_decode($line, true, flags: JSON_THROW_ON_ERROR));
                } catch (JsonException) {
                    continue;
                }
                $postId = $record['post_id'] ?? null;
                if (
                    $record !== null
                    && ($record['location'] ?? null) === $location
                    && is_int($postId)
                    && isset($wantedPostIds[$postId])
                ) {
                    $results[$postId] = $record;
                }
            }
        }

        return $results;
    }

    public function findPostIds(string $location, string $field, string $value): array
    {
        $postIds = [];
        foreach (glob(rtrim($this->directory, '/') . '/*/*/*.jsonl') ?: [] as $filename) {
            $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                continue;
            }
            foreach ($lines as $line) {
                try {
                    $record = $this->sanitizeRecord(json_decode($line, true, flags: JSON_THROW_ON_ERROR));
                } catch (JsonException) {
                    continue;
                }
                $postId = $record['post_id'] ?? null;
                if (
                    $record !== null
                    && ($record['location'] ?? null) === $location
                    && ($record[$field] ?? null) === $value
                    && is_int($postId)
                ) {
                    $postIds[] = $postId;
                }
            }
        }

        return $postIds;
    }

    /** @return array<string, bool|int|string|null>|null */
    private function sanitizeRecord(mixed $record): ?array
    {
        if (!is_array($record)) {
            return null;
        }
        $sanitized = [];
        foreach ($record as $key => $value) {
            $isScalarOrNull = is_bool($value) || is_int($value) || is_string($value) || $value === null;
            if (!is_string($key) || !$isScalarOrNull) {
                return null;
            }
            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private function prune(int $retentionDays): void
    {
        $cutoff = (new DateTimeImmutable('today', new DateTimeZone($this->appTimezone)))
            ->modify(sprintf('-%d days', $retentionDays - 1))
            ->format('Y/m/d');
        foreach (glob(rtrim($this->directory, '/') . '/*/*/*.jsonl') ?: [] as $filename) {
            $relative = substr($filename, strlen(rtrim($this->directory, '/')) + 1, -6);
            if ($relative < $cutoff) {
                unlink($filename);
            }
        }
    }
}
