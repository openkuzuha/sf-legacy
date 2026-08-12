<?php

use App\Post\DailyPostArchive;
use App\Post\PostRecordCodec;

test('投稿をローカル時刻の日別に保存し月単位では結合して読む', function () {
    $directory = sys_get_temp_dir() . '/sf-legacy-archive-' . bin2hex(random_bytes(8));
    $archive = new DailyPostArchive($directory, new PostRecordCodec(), 'Asia/Tokyo');
    $record = [
        'posted_at' => '2026-07-31T15:30:00Z',
        'post_id' => 1,
        'thread_id' => 1,
        'location' => 'main',
        'host' => null,
        'user_agent' => null,
        'author' => '投稿者',
        'email' => '',
        'title' => '',
        'message' => '本文',
        'auto_link' => true,
        'reply_to' => null,
    ];

    try {
        expect($archive->put($record))->toBeTrue()
            ->and($archive->put($record))->toBeFalse()
            ->and($directory . '/2026/08/01.jsonl')->toBeFile()
            ->and($archive->month('2026-08'))->toBe([$record]);
    } finally {
        if (is_file($directory . '/2026/08/01.jsonl')) {
            unlink($directory . '/2026/08/01.jsonl');
        }
        @rmdir($directory . '/2026/08');
        @rmdir($directory . '/2026');
        @rmdir($directory);
    }
});
