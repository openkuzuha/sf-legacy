<?php

namespace App\Post;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/**
 * The deliberately simple text implementation: one UTF-8 JSON object per line.
 *
 * @phpstan-import-type PostInput from PostTypes
 * @phpstan-import-type PostRecord from PostTypes
 */
final class JsonlPostRepository implements PostRepository
{
    public function __construct(
        private readonly string $filename,
        private readonly PostRecordCodec $codec,
        private readonly string $location = 'main',
        private readonly ?PostArchive $archive = null,
        private readonly int $maximumRecords = 500,
    ) {
        if ($this->maximumRecords < 1) {
            throw new RuntimeException('マスターログの最大件数は1以上で指定してください。');
        }
    }

    /** @param PostInput $post */
    public function append(array $post): int
    {
        $directory = dirname($this->filename);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('ログディレクトリ "%s" を作成できません。', $directory));
        }

        $handle = fopen($this->filename, 'c+');
        if ($handle === false) {
            throw new RuntimeException(sprintf('ログファイル "%s" を開けません。', $this->filename));
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('ログファイルをロックできません。');
            }

            $postId = $this->nextPostId($handle);
            $record = $this->record($postId, $post);
            if (fseek($handle, 0, SEEK_END) !== 0 || fwrite($handle, $this->codec->encode($record) . "\n") === false) {
                throw new RuntimeException('投稿をログファイルへ書き込めません。');
            }

            fflush($handle);
            $this->archive?->put($record);
            $this->trim($handle);
            flock($handle, LOCK_UN);

            return $postId;
        } finally {
            fclose($handle);
        }
    }

    /** @param PostRecord $post */
    public function import(array $post): bool
    {
        $directory = dirname($this->filename);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('ログディレクトリ "%s" を作成できません。', $directory));
        }

        $handle = fopen($this->filename, 'c+');
        if ($handle === false) {
            throw new RuntimeException(sprintf('ログファイル "%s" を開けません。', $this->filename));
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('ログファイルをロックできません。');
            }
            rewind($handle);
            while (($line = fgets($handle)) !== false) {
                $existing = $this->codec->decode($line);
                if (
                    $existing !== null
                    && $existing['location'] === $post['location']
                    && $existing['post_id'] === $post['post_id']
                ) {
                    if ($existing === $post) {
                        $this->trim($handle);

                        return false;
                    }
                    throw new RuntimeException(sprintf('投稿ID %d は異なる内容で既に存在します。', $post['post_id']));
                }
            }
            if ($this->archive !== null && !$this->archive->put($post)) {
                return false;
            }
            if (fseek($handle, 0, SEEK_END) !== 0 || fwrite($handle, $this->codec->encode($post) . "\n") === false) {
                throw new RuntimeException('投稿をログファイルへ書き込めません。');
            }
            fflush($handle);
            $this->trim($handle);

            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @return list<PostRecord> */
    public function all(): array
    {
        if (!is_file($this->filename)) {
            return [];
        }

        $handle = fopen($this->filename, 'r');
        if ($handle === false) {
            throw new RuntimeException(sprintf('ログファイル "%s" を開けません。', $this->filename));
        }

        $records = [];
        try {
            if (!flock($handle, LOCK_SH)) {
                throw new RuntimeException('ログファイルをロックできません。');
            }
            while (($line = fgets($handle)) !== false) {
                $record = $this->codec->decode($line);
                if ($record !== null && $record['location'] === $this->location) {
                    $records[] = $record;
                }
            }
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        return array_reverse($records);
    }

    /** @return list<PostRecord> */
    public function recent(int $limit): array
    {
        return array_slice($this->all(), 0, max(0, $limit));
    }

    public function trimTo(int $maximumRecords): void
    {
        if ($maximumRecords < 1) {
            throw new RuntimeException('マスターログの最大件数は1以上で指定してください。');
        }
        if (!is_file($this->filename)) {
            return;
        }
        $handle = fopen($this->filename, 'c+');
        if ($handle === false) {
            throw new RuntimeException(sprintf('ログファイル "%s" を開けません。', $this->filename));
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('ログファイルをロックできません。');
            }
            $this->trim($handle, $maximumRecords);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    public function delete(int $postId): bool
    {
        if (!is_file($this->filename)) {
            return false;
        }
        $handle = fopen($this->filename, 'c+');
        if ($handle === false) {
            throw new RuntimeException(sprintf('ログファイル "%s" を開けません。', $this->filename));
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('ログファイルをロックできません。');
            }
            $kept = [];
            $deleted = null;
            while (($line = fgets($handle)) !== false) {
                $record = $this->codec->decode($line);
                if ($record !== null && $record['location'] === $this->location && $record['post_id'] === $postId) {
                    $deleted = $record;
                    continue;
                }
                $kept[] = $line;
            }
            if ($deleted === null) {
                return false;
            }
            if (!ftruncate($handle, 0) || !rewind($handle)) {
                throw new RuntimeException('マスターログから投稿を削除できません。');
            }
            foreach ($kept as $line) {
                if (fwrite($handle, $line) === false) {
                    throw new RuntimeException('マスターログから投稿を削除できません。');
                }
            }
            fflush($handle);
            $this->archive?->delete($deleted);

            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function clear(): int
    {
        if (!is_file($this->filename)) {
            return 0;
        }
        $handle = fopen($this->filename, 'c+');
        if ($handle === false) {
            throw new RuntimeException(sprintf('ログファイル "%s" を開けません。', $this->filename));
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('ログファイルをロックできません。');
            }
            $count = 0;
            while (($line = fgets($handle)) !== false) {
                $record = $this->codec->decode($line);
                if ($record !== null && $record['location'] === $this->location) {
                    ++$count;
                }
            }
            if (!ftruncate($handle, 0)) {
                throw new RuntimeException('マスターログを初期化できません。');
            }
            fflush($handle);

            return $count;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @param resource $handle */
    private function nextPostId($handle): int
    {
        rewind($handle);
        $maximum = 0;
        while (($line = fgets($handle)) !== false) {
            $record = $this->codec->decode($line);
            if ($record !== null) {
                $maximum = max($maximum, $record['post_id']);
            }
        }

        return $maximum + 1;
    }

    /** @param resource $handle */
    private function trim($handle, ?int $maximumRecords = null): void
    {
        $maximumRecords ??= $this->maximumRecords;
        rewind($handle);
        $lines = [];
        while (($line = fgets($handle)) !== false) {
            $lines[] = $line;
        }
        if (count($lines) <= $maximumRecords) {
            return;
        }

        $lines = array_slice($lines, -$maximumRecords);
        if (!ftruncate($handle, 0) || rewind($handle) === false) {
            throw new RuntimeException('マスターログを切り詰められません。');
        }
        foreach ($lines as $line) {
            if (fwrite($handle, $line) === false) {
                throw new RuntimeException('マスターログを切り詰められません。');
            }
        }
        fflush($handle);
    }

    /**
     * @param PostInput $post
     * @return PostRecord
     */
    private function record(int $postId, array $post): array
    {
        return [
            'posted_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
            'post_id' => $postId,
            'thread_id' => $post['thread_id'] ?? $postId,
            'location' => $this->location,
            'host' => $post['host'],
            'user_agent' => $post['user_agent'],
            'author' => $post['author'],
            'author_spoofed' => $post['author_spoofed'] ?? false,
            'email' => $post['email'],
            'title' => $post['title'],
            'message' => $post['message'],
            'auto_link' => $post['auto_link'] ?? true,
            'reply_to' => $post['reply_to'],
        ];
    }
}
