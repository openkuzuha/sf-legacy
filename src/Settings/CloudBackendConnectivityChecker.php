<?php

namespace App\Settings;

use App\Post\S3PostArchive;
use Predis\Client;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

/**
 * クラウドモードを選ぶ前に、実際にValkeyとS3互換ストレージへ到達できるかを確認する。
 *
 * ここで弾かずにCLOUD_MODE=1を確定させると、次のリクエストから管理画面ごと
 * 到達不能になり自分で締め出されてしまうため、原因を問わず失敗はすべて拾う。
 */
final class CloudBackendConnectivityChecker
{
    public function __construct(
        #[Autowire(env: 'VALKEY_URL')]
        private readonly string $valkeyUrl,
        #[Autowire(env: 'ARCHIVE_S3_ENDPOINT')]
        private readonly string $s3Endpoint,
        #[Autowire(env: 'ARCHIVE_S3_REGION')]
        private readonly string $s3Region,
        #[Autowire(env: 'ARCHIVE_S3_BUCKET')]
        private readonly string $s3Bucket,
        #[Autowire(env: 'ARCHIVE_S3_ACCESS_KEY')]
        private readonly string $s3AccessKey,
        #[Autowire(env: 'ARCHIVE_S3_SECRET_KEY')]
        private readonly string $s3SecretKey,
        #[Autowire(env: 'bool:ARCHIVE_S3_PATH_STYLE')]
        private readonly bool $s3PathStyle,
    ) {
    }

    /** @return list<string> 問題がなければ空配列 */
    public function check(): array
    {
        $problems = [];

        try {
            // connect()は失敗時に例外を投げるが、内部のstream_socket_client()は
            // それとは別にE_WARNINGも送出するため、想定内の失敗として抑制する。
            @(new Client($this->valkeyUrl))->connect();
        } catch (Throwable $exception) {
            $problems[] = sprintf('Valkey（%s）に接続できません: %s', $this->valkeyUrl, $exception->getMessage());
        }

        try {
            $client = S3PostArchive::createClient(
                $this->s3Endpoint,
                $this->s3Region,
                $this->s3AccessKey,
                $this->s3SecretKey,
                $this->s3PathStyle,
            );
            $client->headBucket(['Bucket' => $this->s3Bucket]);
        } catch (Throwable $exception) {
            $problems[] = sprintf(
                'S3互換ストレージ（バケット: %s）に接続できません: %s',
                $this->s3Bucket,
                $exception->getMessage(),
            );
        }

        return $problems;
    }
}
