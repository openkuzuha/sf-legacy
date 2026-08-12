<?php

use App\PageView\FilePageViewCounter;

test('ファイルカウンターは初期値から加算して保存する', function () {
    $filename = sys_get_temp_dir() . '/sf-legacy-page-views-' . bin2hex(random_bytes(8)) . '.txt';
    $counter = new FilePageViewCounter($filename, 20_310_962);

    try {
        expect($counter->increment())->toBe(20_310_963)
            ->and($counter->increment())->toBe(20_310_964)
            ->and(file_get_contents($filename))->toBe('20310964');
    } finally {
        if (is_file($filename)) {
            unlink($filename);
        }
    }
});

test('不正な保存値は初期値から復旧する', function () {
    $filename = sys_get_temp_dir() . '/sf-legacy-page-views-' . bin2hex(random_bytes(8)) . '.txt';
    file_put_contents($filename, 'broken');

    try {
        expect((new FilePageViewCounter($filename, 100))->increment())->toBe(101);
    } finally {
        unlink($filename);
    }
});
