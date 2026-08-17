<?php

namespace App\Post;

use App\Settings\SiteSettings;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Psr\Log\LoggerInterface;

final class PostRepositoryFactory
{
    public function __construct(
        private readonly PostRecordCodec $codec,
        private readonly PostArchive $archive,
        private readonly LoggerInterface $logger,
        private readonly SiteSettings $settings,
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
            $posts = new ArchivedPostRepository(
                new ValkeyPostRepository($valkeyUrl, $this->codec),
                $this->archive,
                $this->logger,
            );
        } else {
            $posts = new JsonlPostRepository(
                $jsonlFilename,
                $this->codec,
                archive: $this->archive,
                maximumRecords: SiteSettings::MAX_CENTRAL_POST_LIMIT,
            );
        }

        return new LimitedPostRepository($posts, $this->settings);
    }
}
