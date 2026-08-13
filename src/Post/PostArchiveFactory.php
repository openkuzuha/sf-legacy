<?php

namespace App\Post;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PostArchiveFactory
{
    public function __construct(private readonly PostRecordCodec $codec)
    {
    }

    public function create(
        #[Autowire(param: 'app.cloud_mode')] bool $cloudMode,
        #[Autowire(param: 'app.timezone')] string $appTimezone,
        #[Autowire(param: 'app.post_archive_directory')] string $directory,
        #[Autowire(env: 'ARCHIVE_S3_ENDPOINT')] string $endpoint,
        #[Autowire(env: 'ARCHIVE_S3_REGION')] string $region,
        #[Autowire(env: 'ARCHIVE_S3_BUCKET')] string $bucket,
        #[Autowire(env: 'ARCHIVE_S3_PREFIX')] string $prefix,
        #[Autowire(env: 'ARCHIVE_S3_ACCESS_KEY')] string $accessKey,
        #[Autowire(env: 'ARCHIVE_S3_SECRET_KEY')] string $secretKey,
        #[Autowire(env: 'bool:ARCHIVE_S3_PATH_STYLE')] bool $pathStyle,
    ): PostArchive {
        if (!$cloudMode) {
            return new DailyPostArchive($directory, $this->codec, $appTimezone);
        }

        return new S3PostArchive(
            S3PostArchive::createClient($endpoint, $region, $accessKey, $secretKey, $pathStyle),
            $bucket,
            $prefix,
            $this->codec,
            $appTimezone,
        );
    }
}
