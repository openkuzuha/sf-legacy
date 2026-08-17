<?php

use App\Post\AuthorNamePolicy;

test('管理者名を含む一般投稿者名を騙り表示用に変換する', function () {
    $policy = new AuthorNamePolicy();

    expect($policy->forGeneralPost('自称・管理人です', '管理人'))->toBe([
        'author' => '管理人（騙り）',
        'spoofed' => true,
    ])->and($policy->forGeneralPost('通りすがり', '管理人'))->toBe([
        'author' => '通りすがり',
        'spoofed' => false,
    ])->and($policy->forGeneralPost('管理人', ''))->toBe([
        'author' => '管理人',
        'spoofed' => false,
    ]);
});
