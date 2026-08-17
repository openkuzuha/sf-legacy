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
