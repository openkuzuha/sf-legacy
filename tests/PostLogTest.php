<?php

use App\Post\JsonlPostRepository;
use App\Post\PostRecordCodec;

test('投稿を旧ログ互換のフィールドを持つJSONLとして追記する', function () {
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
        ]))->toBe(1);

        expect($log->append([
            'author' => '二人目',
            'email' => '',
            'title' => '',
            'message' => '続き',
            'host' => null,
            'user_agent' => null,
        ]))->toBe(2);

        $lines = file($filename, FILE_IGNORE_NEW_LINES);
        expect($lines)->toBeArray();
        assert(is_array($lines));
        expect($lines)->toHaveCount(2);

        $record = json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR);
        expect($record)->toBeArray();
        assert(is_array($record));
        expect($record)
            ->not->toHaveKey('protect')
            ->and($record['post_id'])->toBe(1)
            ->and($record['thread_id'])->toBe(1)
            ->and($record['location'])->toBe('main')
            ->and($record['message'])->toBe("本文,です\n二行目")
            ->and($record['reply_to'])->toBeNull();

        $records = $log->all();
        expect($records)
            ->toHaveCount(2)
            ->and($records[0]['post_id'])->toBe(2)
            ->and($records[1]['post_id'])->toBe(1);

        $imported = $records[0];
        expect($log->import($imported))->toBeFalse();

        $imported['post_id'] = 10;
        $imported['thread_id'] = 10;
        $imported['posted_at'] = 1_700_000_000;
        expect($log->import($imported))->toBeTrue()
            ->and($log->all()[0]['post_id'])->toBe(10)
            ->and($log->append([
                'author' => '採番確認',
                'email' => '',
                'title' => '',
                'message' => '',
                'host' => null,
                'user_agent' => null,
            ]))->toBe(11);
    } finally {
        if (is_file($filename)) {
            unlink($filename);
        }
    }
});
