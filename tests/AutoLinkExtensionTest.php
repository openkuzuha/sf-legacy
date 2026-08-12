<?php

use App\Twig\AutoLinkExtension;

test('URLを安全な外部リンクへ変換する', function () {
    $extension = new AutoLinkExtension();

    expect($extension->autoLink('本文 <script> https://example.com/path?q=1&lang=ja。'))
        ->toBe(
            '本文 &lt;script&gt; '
            . '<a href="https://example.com/path?q=1&amp;lang=ja" target="_blank" rel="noopener noreferrer">'
            . 'https://example.com/path?q=1&amp;lang=ja</a>。',
        );
});

test('URLでない文字列はリンクにしない', function () {
    $extension = new AutoLinkExtension();

    expect($extension->autoLink('example.com ftp://example.com'))->toBe('example.com ftp://example.com');
});
