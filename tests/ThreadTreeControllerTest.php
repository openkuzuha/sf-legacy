<?php

use App\Post\JsonlPostRepository;
use App\Post\PostRecordCodec;
use App\Post\PostRepository;
use App\Tests\TestCase;

test('個別スレッドをreply_toに従って階層表示する', function () {
    /** @var TestCase $this */
    $filename = sys_get_temp_dir() . '/sf-legacy-tree-' . bin2hex(random_bytes(8)) . '.jsonl';
    $repository = new JsonlPostRepository($filename, new PostRecordCodec());
    $base = [
        'posted_at' => '2026-08-12T02:24:41Z',
        'thread_id' => 100,
        'location' => 'main',
        'host' => null,
        'user_agent' => null,
        'author' => '投稿者',
        'email' => '',
        'auto_link' => true,
    ];

    try {
        $repository->import($base + [
            'post_id' => 100,
            'title' => '起点',
            'message' => '最初の投稿',
            'reply_to' => null,
        ]);
        $repository->import($base + [
            'post_id' => 101,
            'title' => '返信',
            'message' => "> 最初の投稿\n一段目",
            'reply_to' => 100,
        ]);
        $repository->import($base + [
            'post_id' => 102,
            'title' => '孫返信',
            'message' => '二段目',
            'reply_to' => 101,
        ]);

        $client = $this->createClient();
        $client->disableReboot();
        $this->getContainer()->set(PostRepository::class, $repository);
        $crawler = $client->request('GET', '/tree/100');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextSame('title', 'Open Kuzuha');
        $this->assertSelectorCount(0, '.page-header');
        $this->assertSelectorCount(1, '.tree-branches > li > #m100');
        $this->assertSelectorCount(1, '.tree-branches > li > .tree-branches > li > #m101');
        $this->assertSelectorTextNotContains('#m101', '> 最初の投稿');
        $this->assertSelectorTextContains('#m101', '一段目');
        $this->assertSelectorExists('#m101 a[title="Reply"][href="/reply/101?return_to=/tree/100"]');
        $this->assertSelectorCount(0, '#m101 a[title="スレッド表示"]');
        $this->assertSelectorExists('#m100 a[title="Reply"][href="/reply/100?return_to=/tree/100"]');
        $this->assertSelectorExists('.tree-updated a[title="スレッド表示"][href="/thread/100"]');
        $this->assertSelectorTextContains('.tree-updated', '[更新日：2026/08/12(水) 11:24:41]');
        $this->assertSelectorCount(
            1,
            '.tree-branches > li > .tree-branches > li > .tree-branches > li > #m102',
        );
        $this->assertSelectorExists('footer#page-bottom');

        $client->request('GET', '/tree/100?p=101');
        $this->assertSelectorCount(1, '#m102 pre.tree-unread-message');
        $this->assertSelectorCount(0, '#m101 pre.tree-unread-message');

        $this->assertSelectorTextContains('footer .request-duration', '実行時間 : ');
        $this->assertSelectorTextSame('main > a[href="/"]', '戻る');
        $this->assertSelectorTextSame('footer a[href="#page-top"]', '▲');
        $replyCrawler = $client->click($crawler->filter('#m101 a[title="Reply"]')->link());
        $this->assertSelectorExists('#post-form input[name="return_to"][value="/tree/100"]');
        $client->submit($replyCrawler->selectButton('投稿／リロード')->form([
            'content' => '個別ツリーからの返信',
        ]));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextSame('.page-title a[href="/tree/100"]', '書き込み完了');
    } finally {
        if (is_file($filename)) {
            unlink($filename);
        }
    }
});

test('全ツリーをスレッドの更新順で階層表示する', function () {
    /** @var TestCase $this */
    $filename = sys_get_temp_dir() . '/sf-legacy-all-trees-' . bin2hex(random_bytes(8)) . '.jsonl';
    $repository = new JsonlPostRepository($filename, new PostRecordCodec());
    $base = [
        'location' => 'main',
        'host' => null,
        'user_agent' => null,
        'author' => '',
        'email' => '',
        'title' => '',
        'auto_link' => true,
    ];

    try {
        $repository->import($base + [
            'posted_at' => '2026-08-12T02:00:00Z',
            'post_id' => 100,
            'thread_id' => 100,
            'message' => '古いスレッド',
            'reply_to' => null,
        ]);
        $repository->import($base + [
            'posted_at' => '2026-08-12T04:00:00Z',
            'post_id' => 101,
            'thread_id' => 100,
            'message' => '古いスレッドへの返信',
            'reply_to' => 100,
        ]);
        $repository->import($base + [
            'posted_at' => '2026-08-12T03:00:00Z',
            'post_id' => 200,
            'thread_id' => 200,
            'message' => '新しいスレッド',
            'reply_to' => null,
        ]);

        $client = $this->createClient();
        $client->disableReboot();
        $this->getContainer()->set(PostRepository::class, $repository);
        $crawler = $client->request('GET', '/tree');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorCount(2, '.tree-thread');
        $this->assertSelectorCount(1, '.tree-thread + hr');
        $this->assertSelectorExists('#post-form input[name="return_to"][value="/tree"]');
        $this->assertSelectorExists('#m200 a[title="Reply"][href="/reply/200?return_to=/tree"]');
        $this->assertSelectorExists('.tree-thread .tree-updated a[title="スレッド表示"][href="/thread/200"]');
        $this->assertSelectorCount(1, '#m100 + .tree-branches > li > #m101');
        expect($crawler->filter('.tree-thread')->eq(0)->filter('.tree-post')->first()->attr('id'))
            ->toBe('m100');

        $repository->import($base + [
            'posted_at' => '2026-08-12T05:00:00Z',
            'post_id' => 201,
            'thread_id' => 100,
            'message' => '未読の返信',
            'reply_to' => 101,
        ]);

        $client->request('GET', '/tree?p=200');
        $this->assertSelectorCount(2, '.tree-thread');
        $this->assertSelectorCount(1, '#m201 pre.tree-unread-message');
        $this->assertSelectorCount(0, '#m200 pre.tree-unread-message');

        $client->submit($crawler->filter('#unread-form')->form());
        $this->assertSelectorCount(1, '.tree-thread');
        $this->assertSelectorCount(0, '#m200');
        $this->assertSelectorCount(1, '#m100');
        $this->assertSelectorCount(1, '#m101');
        $this->assertSelectorCount(1, '#m201 pre.tree-unread-message');
        $this->assertSelectorCount(0, '#m100 pre.tree-unread-message');
        $this->assertSelectorCount(0, '#m101 pre.tree-unread-message');
        $this->assertInputValueSame('p', '201');

        $client->request('GET', '/tree?readnew=1&p=201');
        $this->assertSelectorTextSame('main > p', '未読メッセージはありません。');

        $crawler = $client->request('GET', '/tree');

        $replyCrawler = $client->click($crawler->filter('#m200 a[title="Reply"]')->link());
        $this->assertSelectorExists('#post-form input[name="return_to"][value="/tree"]');
        $client->submit($replyCrawler->selectButton('投稿／リロード')->form([
            'content' => '全体ツリーからの返信',
        ]));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextSame('.page-title a[href="/tree"]', '書き込み完了');

        $crawler = $client->request('GET', '/tree');
        $client->submitForm('投稿／リロード', ['content' => 'ツリーからの投稿']);
        $this->assertResponseRedirects('/tree');

        $crawler = $client->followRedirect();
        $client->submit($crawler->selectButton('投稿／リロード')->form());
        $this->assertResponseRedirects('/tree');
    } finally {
        if (is_file($filename)) {
            unlink($filename);
        }
    }
});

test('◆はスレッド内の記事を新着順で表示する', function () {
    /** @var TestCase $this */
    $filename = sys_get_temp_dir() . '/sf-legacy-thread-' . bin2hex(random_bytes(8)) . '.jsonl';
    $repository = new JsonlPostRepository($filename, new PostRecordCodec());
    $base = [
        'posted_at' => '2026-08-12T02:24:41Z',
        'thread_id' => 100,
        'location' => 'main',
        'host' => null,
        'user_agent' => null,
        'author' => '投稿者',
        'email' => '',
        'auto_link' => true,
    ];

    try {
        $repository->import($base + [
            'post_id' => 100,
            'title' => '起点',
            'message' => '最初の投稿',
            'reply_to' => null,
        ]);
        $repository->import($base + [
            'post_id' => 101,
            'title' => '返信',
            'message' => '返信本文',
            'reply_to' => 100,
        ]);

        $client = $this->createClient();
        $this->getContainer()->set(PostRepository::class, $repository);
        $crawler = $client->request('GET', '/thread/100');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorCount(0, '.page-header');
        $this->assertSelectorCount(2, '.m');
        expect($crawler->filter('.m')->eq(0)->attr('id'))->toBe('m101');
        $this->assertSelectorTextContains('#m101', '返信本文');
        $this->assertSelectorExists('#m101 a[href="/reply/100"]');
        $this->assertSelectorTextContains('p', '2件見つかりました。　←戻る');
    } finally {
        if (is_file($filename)) {
            unlink($filename);
        }
    }
});
