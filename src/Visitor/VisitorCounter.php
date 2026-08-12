<?php

namespace App\Visitor;

final class VisitorCounter
{
    public function __construct(
        private readonly string $filename,
        private readonly int $limit,
    ) {
    }

    public function limit(): int
    {
        return $this->limit;
    }

    public function count(string $clientAddress, ?int $currentTime = null): int
    {
        $currentTime ??= time();
        $directory = dirname($this->filename);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('参加者記録ディレクトリを作成できません: %s', $directory));
        }

        $file = fopen($this->filename, 'c+');
        if ($file === false) {
            throw new \RuntimeException(sprintf('参加者記録ファイルを開けません: %s', $this->filename));
        }

        try {
            if (!flock($file, LOCK_EX)) {
                throw new \RuntimeException('参加者記録ファイルをロックできません。');
            }

            $contents = stream_get_contents($file);
            $decoded = $contents === false || $contents === '' ? [] : json_decode($contents, true);
            $visitors = is_array($decoded) ? $decoded : [];
            $threshold = $currentTime - $this->limit;
            $visitors = array_filter(
                $visitors,
                static fn (mixed $visitedAt): bool => is_int($visitedAt) && $visitedAt >= $threshold,
            );
            $visitors[hash('sha256', $clientAddress)] = $currentTime;

            rewind($file);
            if (!ftruncate($file, 0)) {
                throw new \RuntimeException('参加者記録ファイルを更新できません。');
            }
            $json = json_encode($visitors, JSON_THROW_ON_ERROR);
            if (fwrite($file, $json) === false) {
                throw new \RuntimeException('参加者記録ファイルへ書き込めません。');
            }
            fflush($file);
            flock($file, LOCK_UN);

            return count($visitors);
        } finally {
            fclose($file);
        }
    }
}
