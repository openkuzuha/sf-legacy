<?php

namespace App\Audit;

use Aws\S3\S3Client;
use JsonException;
use Predis\Client;
use RuntimeException;

final class LegacyAuditScrubber
{
    private readonly ?Client $valkey;
    private readonly ?S3Client $s3;

    public function __construct(
        private readonly bool $cloudMode,
        private readonly string $postsFilename,
        private readonly string $archiveDirectory,
        string $valkeyUrl,
        string $s3Endpoint,
        string $s3Region,
        private readonly string $s3Bucket,
        private readonly string $s3Prefix,
        string $s3AccessKey,
        string $s3SecretKey,
        bool $s3PathStyle,
    ) {
        $this->valkey = $cloudMode ? new Client($valkeyUrl) : null;
        $this->s3 = $cloudMode ? new S3Client([
            'version' => 'latest',
            'region' => $s3Region,
            'endpoint' => $s3Endpoint,
            'use_path_style_endpoint' => $s3PathStyle,
            'credentials' => ['key' => $s3AccessKey, 'secret' => $s3SecretKey],
        ]) : null;
    }

    /** @return array{storage:string, scanned:int, affected:int} */
    public function run(bool $apply): array
    {
        return $this->cloudMode ? $this->scrubCloud($apply) : $this->scrubFiles($apply);
    }

    /** @return array{storage:string, scanned:int, affected:int} */
    private function scrubFiles(bool $apply): array
    {
        $files = is_file($this->postsFilename) ? [$this->postsFilename] : [];
        array_push($files, ...(glob(rtrim($this->archiveDirectory, '/') . '/*/*/*.jsonl') ?: []));
        $scanned = 0;
        $affected = 0;
        foreach ($files as $filename) {
            $lines = file($filename);
            if ($lines === false) {
                throw new RuntimeException(sprintf('ファイルを読み込めません: %s', $filename));
            }
            $rewritten = [];
            foreach ($lines as $line) {
                [$clean, $changed] = $this->scrubJson($line);
                ++$scanned;
                $affected += (int) $changed;
                $rewritten[] = $clean . "\n";
            }
            if ($apply && $rewritten !== $lines) {
                $temporary = $filename . '.audit-scrub-' . bin2hex(random_bytes(6));
                if (file_put_contents($temporary, implode('', $rewritten), LOCK_EX) === false) {
                    throw new RuntimeException(sprintf('一時ファイルへ書き込めません: %s', $filename));
                }
                chmod($temporary, fileperms($filename) & 0777);
                if (!rename($temporary, $filename)) {
                    throw new RuntimeException(sprintf('ファイルを置き換えられません: %s', $filename));
                }
            }
        }

        return ['storage' => 'JSON Lines', 'scanned' => $scanned, 'affected' => $affected];
    }

    /** @return array{storage:string, scanned:int, affected:int} */
    private function scrubCloud(bool $apply): array
    {
        if ($this->valkey === null || $this->s3 === null) {
            throw new RuntimeException('クラウドストレージを初期化できません。');
        }
        $scanned = 0;
        $affected = 0;
        foreach ($this->valkey->keys('bbs:main:post:*') as $key) {
            if (!is_string($key)) {
                continue;
            }
            $value = $this->valkey->get($key);
            if (!is_string($value)) {
                continue;
            }
            [$clean, $changed] = $this->scrubJson($value);
            ++$scanned;
            $affected += (int) $changed;
            if ($apply && $changed) {
                $this->valkey->set($key, $clean);
            }
        }
        $configuredPrefix = trim($this->s3Prefix, '/');
        $prefix = ($configuredPrefix === '' ? '' : $configuredPrefix . '/') . 'main/';
        $token = null;
        do {
            $arguments = ['Bucket' => $this->s3Bucket, 'Prefix' => $prefix];
            if ($token !== null) {
                $arguments['ContinuationToken'] = $token;
            }
            $result = $this->s3->listObjectsV2($arguments);
            $contents = $result['Contents'] ?? [];
            if (!is_iterable($contents)) {
                throw new RuntimeException('S3から不正なオブジェクト一覧が返されました。');
            }
            foreach ($contents as $object) {
                if (!is_array($object)) {
                    continue;
                }
                $key = $object['Key'] ?? null;
                if (!is_string($key)) {
                    continue;
                }
                $stored = $this->s3->getObject(['Bucket' => $this->s3Bucket, 'Key' => $key]);
                $bodyValue = $stored['Body'] ?? null;
                if (!is_string($bodyValue) && !$bodyValue instanceof \Stringable) {
                    throw new RuntimeException('S3から不正な投稿データが返されました。');
                }
                $body = (string) $bodyValue;
                [$clean, $changed] = $this->scrubJson($body);
                ++$scanned;
                $affected += (int) $changed;
                if ($apply && $changed) {
                    $this->s3->putObject([
                        'Bucket' => $this->s3Bucket,
                        'Key' => $key,
                        'Body' => $clean,
                        'ContentType' => 'application/json',
                    ]);
                }
            }
            $nextToken = $result['NextContinuationToken'] ?? null;
            $token = ($result['IsTruncated'] ?? false) && is_string($nextToken) ? $nextToken : null;
        } while ($token !== null && $token !== '');

        return ['storage' => 'Valkey / S3', 'scanned' => $scanned, 'affected' => $affected];
    }

    /** @return array{string, bool} */
    private function scrubJson(string $json): array
    {
        try {
            $record = json_decode(trim($json), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('不正な投稿JSONがあるため処理を中止しました。', previous: $exception);
        }
        if (!is_array($record)) {
            throw new RuntimeException('不正な投稿JSONがあるため処理を中止しました。');
        }
        $changed = array_key_exists('host', $record) || array_key_exists('user_agent', $record);
        unset($record['host'], $record['user_agent']);

        return [json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $changed];
    }
}
