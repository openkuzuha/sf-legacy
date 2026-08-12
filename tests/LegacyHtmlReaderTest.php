<?php

use App\Import\LegacyHtmlReader;

test('旧掲示板の公開HTMLから投稿と返信関係を読み取る', function () {
    $html = <<<'HTML'
        <div class="m" id="m100">
        <span class="nw"><span class="ms">最初</span>&nbsp;&nbsp;<span class="mu">
        投稿者：<span class="mun">名無し</span>&nbsp; 投稿日時：2026/08/12(水) 11:24:41</span></span>
        <blockquote><pre class="msgnormal">本文 &amp; 続き</pre></blockquote><!--  -->
        <div class="m" id="m101">
        <span class="nw"><span class="ms">返信</span>&nbsp;&nbsp;<span class="mu">投稿者：
        <span class="mun"><a href="mailto:test@example.com">名前 <span class="mut">◆trip</span></a></span>&nbsp;
        投稿日時：2026/08/12(水) 11:25:00</span></span><blockquote><pre class="msgnormal">
        <span class="q">&gt; 本文</span>
        返信です<a href="#a100">参考：2026/08/12(水) 11:24:41</a></pre></blockquote><!--  -->
        HTML;

    $posts = (new LegacyHtmlReader())->read($html, 'archive');

    expect($posts)->toHaveCount(2)
        ->and($posts[0]['post_id'])->toBe(100)
        ->and($posts[0]['thread_id'])->toBe(100)
        ->and($posts[0]['location'])->toBe('archive')
        ->and($posts[0]['message'])->toBe('本文 & 続き')
        ->and($posts[1]['reply_to'])->toBe(100)
        ->and($posts[1]['thread_id'])->toBe(100)
        ->and($posts[1]['author'])->toBe('名前 ◆trip')
        ->and($posts[1]['email'])->toBe('test@example.com')
        ->and($posts[1]['message'])->toContain('> 本文')
        ->and($posts[1]['message'])->not->toContain('参考：');
});
