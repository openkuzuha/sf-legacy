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

test('検索語を安全にハイライトする', function () {
    $extension = new AutoLinkExtension();

    expect($extension->highlight('本文と本文<script>', '本文'))
        ->toBe(
            '<mark class="search-highlight">本文</mark>と'
            . '<mark class="search-highlight">本文</mark>&lt;script&gt;',
        )
        ->and($extension->autoLink('https://example.com/KEY key', 'key'))
        ->toContain('<mark class="search-highlight">KEY</mark>')
        ->toContain('<mark class="search-highlight">key</mark>');
});
