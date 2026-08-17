<?php

namespace App\Audit;

use JsonException;
use Predis\Client;
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
}
