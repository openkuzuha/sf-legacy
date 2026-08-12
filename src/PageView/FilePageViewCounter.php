<?php

namespace App\PageView;

use RuntimeException;

final class FilePageViewCounter implements PageViewCounter
{
    public function __construct(
        private readonly string $filename,
        private readonly int $initialValue,
    ) {
    }

    public function increment(): int
    {
        $directory = dirname($this->filename);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('カウンター保存先 "%s" を作成できません。', $directory));
        }

        $handle = fopen($this->filename, 'c+');
        if ($handle === false) {
            throw new RuntimeException(sprintf('カウンターファイル "%s" を開けません。', $this->filename));
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('カウンターファイルをロックできません。');
            }

            $contents = stream_get_contents($handle);
            $current = is_string($contents) && preg_match('/^\d+$/', trim($contents)) === 1
                ? (int) trim($contents)
                : $this->initialValue;
            $next = $current + 1;

            rewind($handle);
            if (!ftruncate($handle, 0) || fwrite($handle, (string) $next) === false) {
                throw new RuntimeException('カウンターファイルを更新できません。');
            }
            fflush($handle);
            flock($handle, LOCK_UN);

            return $next;
        } finally {
            fclose($handle);
        }
    }
}
