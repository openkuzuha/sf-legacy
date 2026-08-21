<?php

namespace App\Audit;

use JsonException;
use Predis\Client;
use Predis\Collection\Iterator\Keyspace;
use Predis\PredisException;
use RuntimeException;

final class ValkeyAuditLog implements AuditLog
{
    private readonly Client $client;

    public function __construct(string $url)
    {
        $this->client = new Client($url);
    }

    public function write(array $record, int $retentionDays): void
    {
        try {
            $value = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $key = sprintf('bbs:audit:%s:%d', $record['location'], $record['post_id']);
            $this->client->setex($key, $retentionDays * 86400, $value);
        } catch (JsonException | PredisException $exception) {
            throw new RuntimeException('Valkeyへ監査情報を保存できません。', previous: $exception);
        }
    }

    public function findByPosts(string $location, array $posts): array
    {
        $postIds = array_column($posts, 'post_id');
        if ($postIds === []) {
            return [];
        }
        $keys = array_map(
            static fn (int $postId): string => sprintf('bbs:audit:%s:%d', $location, $postId),
            $postIds,
        );
        try {
            $values = $this->client->mget($keys);
        } catch (PredisException $exception) {
            throw new RuntimeException('Valkeyから監査情報を取得できません。', previous: $exception);
        }

        $results = [];
        foreach ($postIds as $index => $postId) {
            $value = $values[$index] ?? null;
            if (!is_string($value)) {
                continue;
            }
            try {
                $record = $this->sanitizeRecord(json_decode($value, true, flags: JSON_THROW_ON_ERROR));
            } catch (JsonException) {
                continue;
            }
            if ($record !== null) {
                $results[$postId] = $record;
            }
        }

        return $results;
    }

    public function findPostIds(string $location, string $field, string $value): array
    {
        try {
            $keys = [];
            foreach (new Keyspace($this->client, sprintf('bbs:audit:%s:*', $location)) as $key) {
                if (is_string($key)) {
                    $keys[] = $key;
                }
            }
        } catch (PredisException $exception) {
            throw new RuntimeException('Valkeyから監査情報を検索できません。', previous: $exception);
        }
        if ($keys === []) {
            return [];
        }
        try {
            $values = $this->client->mget($keys);
        } catch (PredisException $exception) {
            throw new RuntimeException('Valkeyから監査情報を検索できません。', previous: $exception);
        }

        $postIds = [];
        foreach ($values as $rawValue) {
            if (!is_string($rawValue)) {
                continue;
            }
            try {
                $record = $this->sanitizeRecord(json_decode($rawValue, true, flags: JSON_THROW_ON_ERROR));
            } catch (JsonException) {
                continue;
            }
            $postId = $record['post_id'] ?? null;
            if ($record !== null && ($record[$field] ?? null) === $value && is_int($postId)) {
                $postIds[] = $postId;
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
}
