<?php

namespace App\Post;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PostRepositoryFactory
{
    public function __construct(
        private readonly PostRecordCodec $codec,
        private readonly DailyPostArchive $archive,
        private readonly int $centralPostLimit,
    ) {
    }

    public function create(
        #[Autowire(env: 'POST_STORAGE')]
        string $storage,
        #[Autowire(env: 'VALKEY_URL')]
        string $valkeyUrl,
        #[Autowire('%kernel.project_dir%/var/data/posts.jsonl')]
        string $jsonlFilename,
    ): PostRepository {
        return match ($storage) {
            'jsonl' => new JsonlPostRepository(
                $jsonlFilename,
                $this->codec,
                archive: $this->archive,
                maximumRecords: $this->centralPostLimit,
            ),
            'valkey' => new ValkeyPostRepository($valkeyUrl, $this->codec),
            default => throw new InvalidArgumentException(sprintf('未対応のPOST_STORAGE "%s" です。', $storage)),
        };
    }
}
