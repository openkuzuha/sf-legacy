<?php

namespace App\Post;

/** @phpstan-import-type PostRecord from PostTypes */
interface PostArchive
{
    /** @param PostRecord $record */
    public function put(array $record): bool;

    /** @param PostRecord $record */
    public function delete(array $record): bool;

    /** @return list<PostRecord> */
    public function month(string $yearMonth): array;

    /** @return list<array{date: string, size: int, formatted_size: string, post_count: int}> */
    public function entries(): array;

    /**
     * @param list<string> $dates
     * @return list<PostRecord>
     */
    public function search(array $dates, string $keyword): array;

    /** @return int 削除した投稿件数 */
    public function clear(): int;
}
