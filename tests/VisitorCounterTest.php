<?php

use App\Visitor\VisitorCounter;

test('指定秒数以内にアクセスしたIPごとの参加者数を返す', function () {
    $filename = sys_get_temp_dir() . '/sf-legacy-visitors-' . bin2hex(random_bytes(8)) . '.json';
    $counter = new VisitorCounter($filename, 300);

    try {
        expect($counter->count('192.0.2.1', 1_000))->toBe(1)
            ->and($counter->count('192.0.2.1', 1_100))->toBe(1)
            ->and($counter->count('192.0.2.2', 1_200))->toBe(2)
            ->and($counter->count('192.0.2.3', 1_401))->toBe(2);

        $contents = file_get_contents($filename);
        expect($contents)->toBeString()
            ->and($contents)->not->toContain('192.0.2.');
    } finally {
        if (is_file($filename)) {
            unlink($filename);
        }
    }
});
