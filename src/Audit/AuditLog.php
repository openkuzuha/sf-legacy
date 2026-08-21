<?php

namespace App\Audit;

interface AuditLog
{
    /** @param array<string, bool|int|string|null> $record */
    public function write(array $record, int $retentionDays): void;

    /**
     * @param list<array{post_id: int, posted_at: string}> $posts
     * @return array<int, array<string, bool|int|string|null>> post_idをキーにした監査記録
     */
    public function findByPosts(string $location, array $posts): array;

    /** @return list<int> 指定フィールドが一致した投稿IDの一覧（順不同） */
    public function findPostIds(string $location, string $field, string $value): array;
}
