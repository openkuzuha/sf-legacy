<?php

use App\Twig\DateTimeExtension;
use Carbon\CarbonImmutable;

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('UTCの投稿日時を設定タイムゾーンで表示する', function () {
    CarbonImmutable::setTestNow('2026-08-12T03:24:41Z');
    $extension = new DateTimeExtension('Asia/Tokyo');

    expect($extension->format('2026-08-12T02:24:41Z'))
        ->toBe('2026/08/12(水) 11:24:41')
        ->and($extension->timeAgo('2026-08-12T02:24:41Z'))
        ->toBe('1時間前');
});
