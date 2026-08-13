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
    $nextRecord = $record;
    $nextRecord['posted_at'] = '2026-08-01T15:30:00Z';
    $nextRecord['post_id'] = 2;
    $nextRecord['thread_id'] = 2;

    try {
        expect($archive->put($record))->toBeTrue()
            ->and($archive->put($record))->toBeFalse()
            ->and($archive->put($nextRecord))->toBeTrue()
            ->and($directory . '/2026/08/01.jsonl')->toBeFile()
            ->and($archive->month('2026-08'))->toBe([$record, $nextRecord])
            ->and($archive->search(['2026/08/02'], '本文'))->toBe([$nextRecord])
            ->and($archive->search(['../../etc/passwd'], '本文'))->toBe([])
            ->and($archive->entries())->toBe([
                [
                    'date' => '2026/08/01',
                    'size' => filesize($directory . '/2026/08/01.jsonl'),
                    'formatted_size' => filesize($directory . '/2026/08/01.jsonl') . ' B',
                    'post_count' => 1,
                ],
                [
                    'date' => '2026/08/02',
                    'size' => filesize($directory . '/2026/08/02.jsonl'),
                    'formatted_size' => filesize($directory . '/2026/08/02.jsonl') . ' B',
                    'post_count' => 1,
                ],
            ])
            ->and($archive->clear())->toBe(2)
            ->and($archive->entries())->toBe([])
            ->and($archive->clear())->toBe(0);
    } finally {
        if (is_file($directory . '/2026/08/01.jsonl')) {
            unlink($directory . '/2026/08/01.jsonl');
        }
        if (is_file($directory . '/2026/08/02.jsonl')) {
            unlink($directory . '/2026/08/02.jsonl');
        }
        @rmdir($directory . '/2026/08');
        @rmdir($directory . '/2026');
        @rmdir($directory);
    }
});
