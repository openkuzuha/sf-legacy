<?php

use App\Twig\DateTimeExtension;

test('UTCの投稿日時を設定タイムゾーンで表示する', function () {
    $extension = new DateTimeExtension('Asia/Tokyo');

    expect($extension->format('2026-08-12T02:24:41Z'))
        ->toBe('2026/08/12(水) 11:24:41');
});
