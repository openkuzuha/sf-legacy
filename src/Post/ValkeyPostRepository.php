<?php

namespace App\Post;

use DateTimeImmutable;
use DateTimeZone;
use Predis\Client;
use Predis\Transaction\MultiExec;
use RuntimeException;

/**
 * @phpstan-import-type PostInput from PostTypes
 * @phpstan-import-type PostRecord from PostTypes
 */
final class ValkeyPostRepository implements PostRepository
{
    private Client $client;

    public function __construct(
        string $url,
        private readonly PostRecordCodec $codec,
        private readonly string $location = 'main',
    ) {
        $this->client = new Client($url);
    }

    /** @param PostInput $post */
    public function append(array $post): int
    {
        $postId = $this->client->incr($this->key('next-id'));
        $record = [
            'posted_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
            'post_id' => $postId,
            'thread_id' => $post['thread_id'] ?? $postId,
            'location' => $this->location,
            'host' => $post['host'],
            'user_agent' => $post['user_agent'],
            'author' => $post['author'],
            'email' => $post['email'],
            'title' => $post['title'],
            'message' => $post['message'],
            'auto_link' => $post['auto_link'] ?? true,
            'reply_to' => $post['reply_to'],
        ];

        $transaction = $this->client->transaction();
        if (!$transaction instanceof MultiExec) {
            throw new RuntimeException('Valkeyトランザクションを開始できません。');
        }
        $transaction->set($this->key('post:' . $postId), $this->codec->encode($record));
        $transaction->zadd($this->key('posts'), [$postId => $postId]);
        $transaction->execute();

        return $postId;
    }

    /** @param PostRecord $post */
    public function import(array $post): bool
    {
        if ($post['location'] !== $this->location) {
            throw new RuntimeException(sprintf('location "%s" はこの保存先へ取り込めません。', $post['location']));
        }

        $result = $this->client->eval(
            <<<'LUA'
            local existing = redis.call('GET', KEYS[1])
            if existing == ARGV[1] then return 0 end
            if existing then return -1 end
            redis.call('SET', KEYS[1], ARGV[1])
            redis.call('ZADD', KEYS[2], ARGV[2], ARGV[2])
            local current = tonumber(redis.call('GET', KEYS[3]) or '0')
            if tonumber(ARGV[2]) > current then redis.call('SET', KEYS[3], ARGV[2]) end
            return 1
            LUA,
            3,
            $this->key('post:' . $post['post_id']),
            $this->key('posts'),
            $this->key('next-id'),
            $this->codec->encode($post),
            (string) $post['post_id'],
        );

        if ($result === -1) {
            throw new RuntimeException(sprintf('投稿ID %d は異なる内容で既に存在します。', $post['post_id']));
        }

        return $result === 1;
    }

    /** @return list<PostRecord> */
    public function all(): array
    {
        return $this->readRange(0, -1);
    }

    /** @return list<PostRecord> */
    public function recent(int $limit): array
    {
        return $limit < 1 ? [] : $this->readRange(0, $limit - 1);
    }

    public function trimTo(int $maximumRecords): void
    {
        if ($maximumRecords < 1) {
            throw new RuntimeException('マスターログの最大件数は1以上で指定してください。');
        }
        $result = $this->client->eval(
            <<<'LUA'
            local ids = redis.call('ZRANGE', KEYS[1], 0, -(tonumber(ARGV[1]) + 1))
            for _, id in ipairs(ids) do
                redis.call('DEL', ARGV[2] .. id)
                redis.call('ZREM', KEYS[1], id)
            end
            return #ids
            LUA,
            1,
            $this->key('posts'),
            (string) $maximumRecords,
            $this->key('post:'),
        );
        if (!is_int($result)) {
            throw new RuntimeException('Valkeyのマスターログを切り詰められません。');
        }
    }

    public function delete(int $postId): bool
    {
        $result = $this->client->eval(
            <<<'LUA'
            if redis.call('DEL', KEYS[1]) == 0 then return 0 end
            redis.call('ZREM', KEYS[2], ARGV[1])
            return 1
            LUA,
            2,
            $this->key('post:' . $postId),
            $this->key('posts'),
            (string) $postId,
        );

        return $result === 1;
    }

    public function clear(): int
    {
        $result = $this->client->eval(
            <<<'LUA'
            local ids = redis.call('ZRANGE', KEYS[1], 0, -1)
            for _, id in ipairs(ids) do
                redis.call('DEL', ARGV[1] .. id)
            end
            redis.call('DEL', KEYS[1], KEYS[2])
            return #ids
            LUA,
            2,
            $this->key('posts'),
            $this->key('next-id'),
            $this->key('post:'),
        );

        if (!is_int($result)) {
            throw new RuntimeException('Valkeyの投稿データを初期化できません。');
        }

        return $result;
    }

    /** @return list<PostRecord> */
    private function readRange(int $start, int $stop): array
    {
        $ids = $this->client->zrevrange($this->key('posts'), $start, $stop);
        if ($ids === []) {
            return [];
        }

        $keys = [];
        foreach ($ids as $id) {
            if (!is_string($id)) {
                throw new RuntimeException('Valkeyに不正な投稿IDがあります。');
            }
            $keys[] = $this->key('post:' . $id);
        }
        $texts = $this->client->mget($keys);
        $records = [];
        foreach ($texts as $text) {
            if (!is_string($text)) {
                throw new RuntimeException('Valkeyの投稿インデックスと投稿本体が一致しません。');
            }
            $record = $this->codec->decode($text);
            if ($record === null) {
                throw new RuntimeException('Valkeyに不正な投稿データがあります。');
            }
            $records[] = $record;
        }

        return $records;
    }

    private function key(string $name): string
    {
        return 'bbs:' . $this->location . ':' . $name;
    }
}
