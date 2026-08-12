<?php

namespace App\Post;

/**
 * @phpstan-import-type PostInput from PostTypes
 * @phpstan-import-type PostRecord from PostTypes
 */
interface PostRepository
{
    /** @param PostInput $post */
    public function append(array $post): int;

    /**
     * 元のIDと日時を保った投稿を取り込む。
     *
     * @param PostRecord $post
     * @return bool 新規に保存した場合はtrue、同一レコードが既にあればfalse
     */
    public function import(array $post): bool;

    /** @return list<PostRecord> */
    public function all(): array;

    /** @return list<PostRecord> */
    public function recent(int $limit): array;
}
