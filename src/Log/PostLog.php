<?php

namespace App\Log;

use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PostLog
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/var/data/posts.jsonl')]
        private readonly string $filename,
    ) {
    }

    /**
     * @param array{
     *     author: string,
     *     email: string,
     *     title: string,
     *     message: string,
     *     host: ?string,
     *     user_agent: ?string
     * } $post
     */
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
            $record = [
                'posted_at' => time(),
                'post_id' => $postId,
                'thread_id' => $postId,
                'host' => $post['host'],
                'user_agent' => $post['user_agent'],
                'author' => $post['author'],
                'email' => $post['email'],
                'title' => $post['title'],
                'message' => $post['message'],
                'reply_to' => null,
            ];
            $json = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            if (fseek($handle, 0, SEEK_END) !== 0 || fwrite($handle, $json . "\n") === false) {
                throw new RuntimeException('投稿をログファイルへ書き込めません。');
            }

            fflush($handle);
            flock($handle, LOCK_UN);

            return $postId;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return list<array{
     *     posted_at: int,
     *     post_id: int,
     *     thread_id: int,
     *     host: ?string,
     *     user_agent: ?string,
     *     author: string,
     *     email: string,
     *     title: string,
     *     message: string,
     *     reply_to: ?int
     * }>
     */
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
                $record = json_decode($line, true);
                if ($this->isPostRecord($record)) {
                    $records[] = $record;
                }
            }

            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        return array_reverse($records);
    }

    /** @param resource $handle */
    private function nextPostId($handle): int
    {
        rewind($handle);
        $maximum = 0;

        while (($line = fgets($handle)) !== false) {
            $record = json_decode($line, true);
            if (is_array($record) && isset($record['post_id']) && is_int($record['post_id'])) {
                $maximum = max($maximum, $record['post_id']);
            }
        }

        return $maximum + 1;
    }

    /**
     * @phpstan-assert-if-true array{
     *     posted_at: int,
     *     post_id: int,
     *     thread_id: int,
     *     host: ?string,
     *     user_agent: ?string,
     *     author: string,
     *     email: string,
     *     title: string,
     *     message: string,
     *     reply_to: ?int
     * } $record
     */
    private function isPostRecord(mixed $record): bool
    {
        return is_array($record)
            && isset($record['posted_at'], $record['post_id'], $record['thread_id'])
            && is_int($record['posted_at'])
            && is_int($record['post_id'])
            && is_int($record['thread_id'])
            && array_key_exists('host', $record)
            && (is_string($record['host']) || $record['host'] === null)
            && array_key_exists('user_agent', $record)
            && (is_string($record['user_agent']) || $record['user_agent'] === null)
            && isset($record['author'], $record['email'], $record['title'], $record['message'])
            && is_string($record['author'])
            && is_string($record['email'])
            && is_string($record['title'])
            && is_string($record['message'])
            && array_key_exists('reply_to', $record)
            && (is_int($record['reply_to']) || $record['reply_to'] === null);
    }
}
