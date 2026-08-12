<?php

namespace App\Post;

use JsonException;

/** @phpstan-import-type PostRecord from PostTypes */
final class PostRecordCodec
{
    /** @param PostRecord $record */
    public function encode(array $record): string
    {
        return json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** @return PostRecord|null */
    public function decode(string $text): ?array
    {
        try {
            $record = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!$this->isPostRecord($record)) {
            return null;
        }

        return $record;
    }

    /** @phpstan-assert-if-true PostRecord $record */
    private function isPostRecord(mixed $record): bool
    {
        return is_array($record)
            && isset($record['posted_at'], $record['post_id'], $record['thread_id'], $record['location'])
            && is_int($record['posted_at'])
            && is_int($record['post_id'])
            && is_int($record['thread_id'])
            && is_string($record['location'])
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
