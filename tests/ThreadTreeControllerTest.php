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
        $this->getContainer()->set(PostRepository::class, $repository);
        $client->request('GET', '/tree/100');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1 a[href="/"]', 'Open Kuzuha');
        $this->assertSelectorCount(1, '.tree-branches > li > #m100');
        $this->assertSelectorCount(1, '.tree-branches > li > .tree-branches > li > #m101');
        $this->assertSelectorTextNotContains('#m101', '> 最初の投稿');
        $this->assertSelectorTextContains('#m101', '一段目');
        $this->assertSelectorCount(
            1,
            '.tree-branches > li > .tree-branches > li > .tree-branches > li > #m102',
        );
    } finally {
        if (is_file($filename)) {
            unlink($filename);
        }
    }
});
