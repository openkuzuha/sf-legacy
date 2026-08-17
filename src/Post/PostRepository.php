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

    /** マスターログだけを新しい投稿から指定件数に切り詰める。 */
    public function trimTo(int $maximumRecords): void;

    /** @return bool 投稿が存在し、削除できた場合はtrue */
    public function delete(int $postId): bool;

    /** @return int 削除した投稿件数 */
    public function clear(): int;
}
