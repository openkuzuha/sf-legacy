<?php

namespace App\Post;

use App\Settings\SiteSettings;

/**
 * アーカイブ処理を含む保存が完了した後で、マスターログだけを設定件数へ切り詰める。
 *
 * @phpstan-import-type PostInput from PostTypes
 * @phpstan-import-type PostRecord from PostTypes
 */
final class LimitedPostRepository implements PostRepository
{
    public function __construct(
        private readonly PostRepository $posts,
        private readonly SiteSettings $settings,
    ) {
    }

    /** @param PostInput $post */
    public function append(array $post): int
    {
        $postId = $this->posts->append($post);
        $this->trimToConfiguredLimit();

        return $postId;
    }

    /** @param PostRecord $post */
    public function import(array $post): bool
    {
        $imported = $this->posts->import($post);
        $this->trimToConfiguredLimit();

        return $imported;
    }

    public function all(): array
    {
        return $this->posts->all();
    }

    public function recent(int $limit): array
    {
        return $this->posts->recent($limit);
    }

    public function trimTo(int $maximumRecords): void
    {
        $this->posts->trimTo($maximumRecords);
    }

    public function delete(int $postId): bool
    {
        return $this->posts->delete($postId);
    }

    public function clear(): int
    {
        return $this->posts->clear();
    }

    private function trimToConfiguredLimit(): void
    {
        $this->posts->trimTo($this->settings->centralPostLimit());
    }
}
