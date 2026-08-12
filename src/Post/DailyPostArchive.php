<?php

namespace App\Post;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/** @phpstan-import-type PostRecord from PostTypes */
final class DailyPostArchive
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

    private function filename(string $postedAt): string
    {
        $date = (new DateTimeImmutable($postedAt))->setTimezone($this->timezone);

        return sprintf('%s/%s.jsonl', rtrim($this->directory, '/'), $date->format('Y/m/d'));
    }
}
