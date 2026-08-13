<?php

namespace App\Post;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Psr\Log\LoggerInterface;

final class PostRepositoryFactory
{
    public function __construct(
        private readonly PostRecordCodec $codec,
        private readonly PostArchive $archive,
        private readonly LoggerInterface $logger,
        private readonly int $centralPostLimit,
    ) {
    }

    public function create(
        #[Autowire(param: 'app.cloud_mode')]
        bool $cloudMode,
        #[Autowire(env: 'VALKEY_URL')]
        string $valkeyUrl,
        #[Autowire('%kernel.project_dir%/var/data/posts.jsonl')]
        string $jsonlFilename,
    ): PostRepository {
        if ($cloudMode) {
            return new ArchivedPostRepository(
                new ValkeyPostRepository($valkeyUrl, $this->codec),
                $this->archive,
                $this->logger,
            );
        }

        return new JsonlPostRepository(
            $jsonlFilename,
            $this->codec,
            archive: $this->archive,
            maximumRecords: $this->centralPostLimit,
        );
    }
}
