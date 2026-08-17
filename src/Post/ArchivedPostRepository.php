<?php

namespace App\Post;

use Psr\Log\LoggerInterface;
use Throwable;

/**
 * 中央ストレージへの書き込みを成立させた後、長期アーカイブへ複製する。
 *
 * @phpstan-import-type PostInput from PostTypes
 * @phpstan-import-type PostRecord from PostTypes
 */
final class ArchivedPostRepository implements PostRepository
{
    public function __construct(
        private readonly PostRepository $posts,
        private readonly PostArchive $archive,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @param PostInput $post */
    public function append(array $post): int
    {
        $postId = $this->posts->append($post);
        foreach ($this->posts->all() as $record) {
            if ($record['post_id'] === $postId) {
                $this->archiveSafely($record);

                return $postId;
            }
        }
        $this->logger->error('中央ストレージへ保存した投稿をアーカイブ用に読み戻せませんでした。', ['post_id' => $postId]);

        return $postId;
    }

    /** @param PostRecord $post */
    public function import(array $post): bool
    {
        $imported = $this->posts->import($post);
        $this->archiveSafely($post);

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
        $record = null;
        foreach ($this->posts->all() as $candidate) {
            if ($candidate['post_id'] === $postId) {
                $record = $candidate;
                break;
            }
        }
        if (!$this->posts->delete($postId)) {
            return false;
        }
        if ($record !== null) {
            try {
                $this->archive->delete($record);
            } catch (Throwable $exception) {
                $this->logger->error('投稿のアーカイブ削除に失敗しました。', [
                    'post_id' => $postId,
                    'exception' => $exception,
                ]);
            }
        }

        return true;
    }

    public function clear(): int
    {
        return $this->posts->clear();
    }

    /** @param PostRecord $record */
    private function archiveSafely(array $record): void
    {
        try {
            $this->archive->put($record);
        } catch (Throwable $exception) {
            $this->logger->error('投稿のアーカイブ保存に失敗しました。', [
                'post_id' => $record['post_id'],
                'exception' => $exception,
            ]);
        }
    }
}
