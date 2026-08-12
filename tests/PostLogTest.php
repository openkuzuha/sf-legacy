<?php

use App\Post\JsonlPostRepository;
use App\Post\PostRecordCodec;

test('投稿日時をUTCのRFC 3339形式でJSONLへ追記する', function () {
    $filename = sys_get_temp_dir() . '/sf-legacy-post-log-' . bin2hex(random_bytes(8)) . '.jsonl';
    $log = new JsonlPostRepository($filename, new PostRecordCodec());

    try {
        expect($log->append([
            'author' => '投稿者',
            'email' => 'test@example.com',
            'title' => '題名',
            'message' => "本文,です\n二行目",
            'host' => '127.0.0.1',
            'user_agent' => 'Test Browser',
            'thread_id' => null,
            'reply_to' => null,
        ]))->toBe(1);

        expect($log->append([
            'author' => '二人目',
            'email' => '',
            'title' => '',
            'message' => '続き',
            'host' => null,
            'user_agent' => null,
            'thread_id' => null,
            'reply_to' => null,
        ]))->toBe(2);

        expect($log->append([
            'author' => '三人目',
            'email' => '',
            'title' => '',
            'message' => '自動リンクなし',
            'auto_link' => false,
            'host' => null,
            'user_agent' => null,
            'thread_id' => null,
            'reply_to' => null,
        ]))->toBe(3);

        $lines = file($filename, FILE_IGNORE_NEW_LINES);
        expect($lines)->toBeArray();
        assert(is_array($lines));
        expect($lines)->toHaveCount(3);

        $record = json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR);
        expect($record)->toBeArray();
        assert(is_array($record));
        expect($record)
            ->not->toHaveKey('protect')
            ->and($record['post_id'])->toBe(1)
            ->and($record['thread_id'])->toBe(1)
            ->and($record['location'])->toBe('main')
            ->and($record['posted_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/')
            ->and($record['message'])->toBe("本文,です\n二行目")
            ->and($record['auto_link'])->toBeTrue()
            ->and($record['reply_to'])->toBeNull();

        $legacyRecord = $record;
        $legacyRecord['posted_at'] = 1_700_000_000;
        expect((new PostRecordCodec())->decode(json_encode($legacyRecord, JSON_THROW_ON_ERROR)))->toBeNull();

        unset($record['auto_link']);
        $decoded = (new PostRecordCodec())->decode(json_encode($record, JSON_THROW_ON_ERROR));
        expect($decoded)->not->toBeNull();
        assert(is_array($decoded));
        expect($decoded['auto_link'])->toBeTrue();

        $records = $log->all();
        expect($records)
            ->toHaveCount(3)
            ->and($records[0]['post_id'])->toBe(3)
            ->and($records[0]['auto_link'])->toBeFalse()
            ->and($records[1]['post_id'])->toBe(2)
            ->and($records[2]['post_id'])->toBe(1);

        $imported = $records[0];
        expect($log->import($imported))->toBeFalse();

        $imported['post_id'] = 10;
        $imported['thread_id'] = 10;
        $imported['posted_at'] = '2023-11-14T22:13:20Z';
        expect($log->import($imported))->toBeTrue()
            ->and($log->all()[0]['post_id'])->toBe(10)
            ->and($log->append([
                'author' => '採番確認',
                'email' => '',
                'title' => '',
                'message' => '',
                'host' => null,
                'user_agent' => null,
                'thread_id' => null,
                'reply_to' => null,
            ]))->toBe(11);
    } finally {
        if (is_file($filename)) {
            unlink($filename);
        }
    }
});

test('中央ログは設定件数に切り詰めても日別アーカイブには全件を残す', function () {
    $directory = sys_get_temp_dir() . '/sf-legacy-post-limit-' . bin2hex(random_bytes(8));
    $filename = $directory . '/posts.jsonl';
    $codec = new PostRecordCodec();
    $archive = new \App\Post\DailyPostArchive($directory . '/archive', $codec, 'UTC');
    $log = new JsonlPostRepository($filename, $codec, archive: $archive, maximumRecords: 2);
    $input = [
        'author' => '',
        'email' => '',
        'title' => '',
        'message' => '',
        'host' => null,
        'user_agent' => null,
        'thread_id' => null,
        'reply_to' => null,
    ];

    try {
        $log->append($input);
        $log->append($input);
        $log->append($input);

        expect($log->all())->toHaveCount(2)
            ->and(array_column($log->all(), 'post_id'))->toBe([3, 2]);
        $archiveFiles = glob($directory . '/archive/*/*/*.jsonl');
        expect($archiveFiles)->toBeArray()->toHaveCount(1);
        assert(is_array($archiveFiles));
        expect(file($archiveFiles[0], FILE_IGNORE_NEW_LINES))->toHaveCount(3);
    } finally {
        if (is_dir($directory)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($iterator as $item) {
                assert($item instanceof SplFileInfo);
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($directory);
        }
    }
});
