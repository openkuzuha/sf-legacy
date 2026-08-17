<?php

namespace App\Post;

use Aws\S3\S3Client;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/** @phpstan-import-type PostRecord from PostTypes */
final class S3PostArchive implements PostArchive
{
    private readonly DateTimeZone $timezone;

    public function __construct(
        private readonly S3Client $client,
        private readonly string $bucket,
        private readonly string $prefix,
        private readonly PostRecordCodec $codec,
        string $appTimezone,
    ) {
        $this->timezone = new DateTimeZone($appTimezone);
    }

    public static function createClient(
        string $endpoint,
        string $region,
        string $accessKey,
        string $secretKey,
        bool $pathStyle,
    ): S3Client {
        return new S3Client([
            'version' => 'latest',
            'region' => $region,
            'endpoint' => $endpoint,
            'use_path_style_endpoint' => $pathStyle,
            'credentials' => ['key' => $accessKey, 'secret' => $secretKey],
        ]);
    }

    public function put(array $record): bool
    {
        $key = $this->key($record);
        $body = $this->codec->encode($record);
        try {
            $existing = $this->client->getObject(['Bucket' => $this->bucket, 'Key' => $key]);
            $existingBody = $this->stringValue($existing['Body']);
            if ($existingBody === $body) {
                return false;
            }
            throw new RuntimeException(sprintf('アーカイブ内の投稿ID %d の内容が一致しません。', $record['post_id']));
        } catch (\Aws\S3\Exception\S3Exception $exception) {
            if ($exception->getStatusCode() !== 404) {
                throw $exception;
            }
        }
        $this->client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'Body' => $body,
            'ContentType' => 'application/json',
        ]);

        return true;
    }

    public function delete(array $record): bool
    {
        $key = $this->key($record);
        try {
            $this->client->headObject(['Bucket' => $this->bucket, 'Key' => $key]);
        } catch (\Aws\S3\Exception\S3Exception $exception) {
            if ($exception->getStatusCode() === 404) {
                return false;
            }
            throw $exception;
        }
        $this->client->deleteObject(['Bucket' => $this->bucket, 'Key' => $key]);

        return true;
    }

    public function month(string $yearMonth): array
    {
        if (preg_match('/^\d{4}-\d{2}$/D', $yearMonth) !== 1) {
            throw new RuntimeException('年月は YYYY-MM 形式で指定してください。');
        }

        return $this->records($this->basePrefix() . str_replace('-', '/', $yearMonth) . '/');
    }

    public function entries(int $recentDays = 0): array
    {
        $days = [];
        $objects = [];
        if ($recentDays > 0) {
            $date = new DateTimeImmutable('today', $this->timezone);
            for ($offset = 0; $offset < $recentDays; ++$offset) {
                $prefix = $this->basePrefix()
                    . $date->modify(sprintf('-%d days', $offset))->format('Y/m/d') . '/';
                array_push($objects, ...$this->objects($prefix));
            }
        } else {
            $objects = $this->objects($this->basePrefix());
        }
        foreach ($objects as $object) {
            $relative = substr($object['key'], strlen($this->basePrefix()));
            if (preg_match('#^(\d{4}/\d{2}/\d{2})/\d+\.json$#D', $relative, $matches) !== 1) {
                continue;
            }
            $date = $matches[1];
            $days[$date] ??= ['size' => 0, 'post_count' => 0];
            $days[$date]['size'] += $object['size'];
            ++$days[$date]['post_count'];
        }
        ksort($days, SORT_STRING);

        $entries = [];
        foreach ($days as $date => $data) {
            $entries[] = [
                'date' => $date,
                'size' => $data['size'],
                'formatted_size' => self::formatBytes($data['size']),
                'post_count' => $data['post_count'],
            ];
        }

        return $entries;
    }

    /**
     * @param list<string> $dates
     * @return list<PostRecord>
     */
    public function search(array $dates, string $keyword): array
    {
        $records = [];
        foreach (array_unique($dates) as $date) {
            if (preg_match('#^\d{4}/\d{2}/\d{2}$#D', $date) !== 1) {
                continue;
            }
            foreach ($this->records($this->basePrefix() . $date . '/') as $record) {
                $target = implode("\n", [$record['author'], $record['email'], $record['title'], $record['message']]);
                if ($keyword === '' || mb_stripos($target, $keyword) !== false) {
                    $records[] = $record;
                }
            }
        }
        usort($records, static fn (array $left, array $right): int => $left['posted_at'] <=> $right['posted_at']);

        return $records;
    }

    public function prune(int $retentionDays): int
    {
        if ($retentionDays === 0) {
            return 0;
        }
        $cutoff = (new DateTimeImmutable('now', $this->timezone))
            ->modify(sprintf('-%d days', $retentionDays - 1))
            ->format('Y/m/d');
        $expired = [];
        foreach ($this->objects($this->basePrefix()) as $object) {
            $relative = substr($object['key'], strlen($this->basePrefix()));
            if (
                preg_match('#^(\d{4}/\d{2}/\d{2})/\d+\.json$#D', $relative, $matches) === 1
                && $matches[1] < $cutoff
            ) {
                $expired[] = $object;
            }
        }
        foreach (array_chunk($expired, 1000) as $chunk) {
            $keys = array_map(static fn (array $object): array => ['Key' => $object['key']], $chunk);
            $this->client->deleteObjects([
                'Bucket' => $this->bucket,
                'Delete' => ['Objects' => $keys],
            ]);
        }

        return count($expired);
    }

    public function clear(): int
    {
        $objects = $this->objects($this->basePrefix());
        foreach (array_chunk($objects, 1000) as $chunk) {
            $keys = array_map(static fn (array $object): array => ['Key' => $object['key']], $chunk);
            $this->client->deleteObjects([
                'Bucket' => $this->bucket,
                'Delete' => ['Objects' => $keys],
            ]);
        }

        return count($objects);
    }

    /** @return list<PostRecord> */
    private function records(string $prefix): array
    {
        $records = [];
        foreach ($this->objects($prefix) as $object) {
            $result = $this->client->getObject(['Bucket' => $this->bucket, 'Key' => $object['key']]);
            $record = $this->codec->decode($this->stringValue($result['Body']));
            if ($record !== null) {
                $records[] = $record;
            }
        }
        usort($records, static fn (array $left, array $right): int => $left['posted_at'] <=> $right['posted_at']);

        return $records;
    }

    /** @return list<array{key: string, size: int}> */
    private function objects(string $prefix): array
    {
        $objects = [];
        $token = null;
        do {
            $arguments = ['Bucket' => $this->bucket, 'Prefix' => $prefix];
            if ($token !== null) {
                $arguments['ContinuationToken'] = $token;
            }
            $result = $this->client->listObjectsV2($arguments);
            $this->collectObjects($result['Contents'] ?? [], $objects);
            $token = $result['IsTruncated'] ? $this->stringValue($result['NextContinuationToken']) : null;
        } while ($token !== null);

        return $objects;
    }

    /** @param list<array{key: string, size: int}> $objects */
    private function collectObjects(mixed $contents, array &$objects): void
    {
        if (!is_array($contents)) {
            throw new RuntimeException('S3から不正なオブジェクト一覧が返されました。');
        }
        foreach ($contents as $object) {
            if (!is_array($object) || !is_string($object['Key'] ?? null) || !is_numeric($object['Size'] ?? null)) {
                throw new RuntimeException('S3から不正なオブジェクト情報が返されました。');
            }
            $objects[] = ['key' => $object['Key'], 'size' => (int) $object['Size']];
        }
    }

    private function stringValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        throw new RuntimeException('S3から文字列ではない値が返されました。');
    }

    /** @param PostRecord $record */
    private function key(array $record): string
    {
        $date = (new DateTimeImmutable($record['posted_at']))->setTimezone($this->timezone);

        return sprintf('%s%s/%010d.json', $this->basePrefix(), $date->format('Y/m/d'), $record['post_id']);
    }

    private function basePrefix(): string
    {
        $prefix = trim($this->prefix, '/');

        return ($prefix === '' ? '' : $prefix . '/') . 'main/';
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return sprintf('%d B', $bytes);
        }
        $units = ['KB', 'MB', 'GB', 'TB'];
        $size = $bytes / 1024;
        foreach ($units as $unit) {
            if ($size < 1024 || $unit === 'TB') {
                return sprintf('%s %s', number_format($size, 1), $unit);
            }
            $size /= 1024;
        }

        return sprintf('%d B', $bytes);
    }
}
