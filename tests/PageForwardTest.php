<?php

use App\Post\JsonlPostRepository;
use App\Post\PostRecordCodec;
use App\Post\PostRepository;
use App\Tests\TestCase;

test('最後の記事を基準に古い記事へ送る', function () {
    /** @var TestCase $this */
    $filename = sys_get_temp_dir() . '/sf-legacy-page-forward-' . bin2hex(random_bytes(8)) . '.jsonl';
    $repository = new JsonlPostRepository($filename, new PostRecordCodec());

    try {
        for ($postId = 1; $postId <= 42; ++$postId) {
            $repository->import([
                'posted_at' => '2026-08-12T02:24:41Z',
                'post_id' => $postId,
                'thread_id' => $postId,
                'location' => 'main',
                'host' => null,
                'user_agent' => null,
                'author' => '',
                'email' => '',
                'title' => '投稿' . $postId,
                'message' => '本文' . $postId,
                'auto_link' => true,
                'reply_to' => null,
            ]);
        }

        $client = $this->createClient();
        $client->disableReboot();
        $this->getContainer()->set(PostRepository::class, $repository);
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorCount(40, '.m');
        $this->assertSelectorCount(1, '#m42');
        $this->assertSelectorCount(1, '#m3');
        $this->assertSelectorTextContains('.msgmore', '新着順1番目から40番目まで');
        $this->assertSelectorExists('.m + hr + .msgmore + .page-actions');
        $this->assertSelectorExists('.page-actions input[name="before"][value="3"]');
        $this->assertSelectorTextContains('.page-actions form:last-of-type button', 'リロード');
        $this->assertSelectorTextContains('.page-actions [aria-label="一番上へ"]', '▲');

        $client->request('GET', '/?before=3');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorCount(2, '.m');
        $this->assertSelectorCount(1, '#m2');
        $this->assertSelectorCount(1, '#m1');
        $this->assertSelectorTextContains('.msgmore', '新着順41番目から42番目まで');
        $this->assertSelectorNotExists('.page-actions input[name="before"]');
        $this->assertSelectorTextContains('.page-actions button', 'リロード');

        $client->submitForm('リロード');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorCount(40, '.m');
        $this->assertSelectorCount(1, '#m42');
    } finally {
        if (is_file($filename)) {
            unlink($filename);
        }
    }
});

test('投稿フォームで表示件数を選択して引き継ぐ', function () {
    /** @var TestCase $this */
    $filename = sys_get_temp_dir() . '/sf-legacy-display-count-' . bin2hex(random_bytes(8)) . '.jsonl';
    $repository = new JsonlPostRepository($filename, new PostRecordCodec());

    try {
        for ($postId = 1; $postId <= 5; ++$postId) {
            $repository->import([
                'posted_at' => '2026-08-12T02:24:41Z',
                'post_id' => $postId,
                'thread_id' => $postId,
                'location' => 'main',
                'host' => null,
                'user_agent' => null,
                'author' => '',
                'email' => '',
                'title' => '投稿' . $postId,
                'message' => '本文' . $postId,
                'auto_link' => true,
                'reply_to' => null,
            ]);
        }

        $client = $this->createClient();
        $client->disableReboot();
        $this->getContainer()->set(PostRepository::class, $repository);
        $crawler = $client->request('GET', '/');

        $this->assertInputValueSame('display_count', '40');
        $form = $crawler->filter('#post-form')->selectButton('投稿／リロード')->last()->form();
        $client->submit($form, ['display_count' => '2']);
        $this->assertResponseRedirects('/?display_count=2');

        $client->followRedirect();
        $this->assertSelectorCount(2, '.m');
        $this->assertInputValueSame('display_count', '2');
        $this->assertSelectorExists('.page-actions input[name="display_count"][value="2"]');

        $client->request('GET', '/');
        $this->assertSelectorCount(2, '.m');
        $this->assertInputValueSame('display_count', '2');
    } finally {
        if (is_file($filename)) {
            unlink($filename);
        }
    }
});

test('画面表示時の最新IDより後に投稿された未読記事だけを表示する', function () {
    /** @var TestCase $this */
    $filename = sys_get_temp_dir() . '/sf-legacy-unread-' . bin2hex(random_bytes(8)) . '.jsonl';
    $repository = new JsonlPostRepository($filename, new PostRecordCodec());
    $base = [
        'posted_at' => '2026-08-12T02:24:41Z',
        'thread_id' => 1,
        'location' => 'main',
        'host' => null,
        'user_agent' => null,
        'author' => '',
        'email' => '',
        'title' => '題名',
        'auto_link' => true,
        'reply_to' => null,
    ];

    try {
        $repository->import($base + ['post_id' => 1, 'message' => '既読']);
        $client = $this->createClient();
        $client->disableReboot();
        $this->getContainer()->set(PostRepository::class, $repository);
        $crawler = $client->request('GET', '/');
        $this->assertInputValueSame('p', '1');

        $repository->import($base + ['post_id' => 2, 'message' => '未読1']);
        $repository->import($base + ['post_id' => 3, 'message' => '未読2']);
        $client->submit($crawler->filter('#unread-form')->form());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorCount(2, '.m');
        $this->assertSelectorCount(0, '#m1');
        $this->assertSelectorCount(1, '#m2');
        $this->assertSelectorCount(1, '#m3');
        $this->assertSelectorTextContains('.msgmore', '画面表示後に投稿された未読記事');
        $this->assertInputValueSame('p', '3');

        $client->request('GET', '/?readnew=1&p=3');
        $this->assertSelectorCount(0, '.m');
        $this->assertSelectorTextSame('.msgmore', '未読メッセージはありません。');
    } finally {
        if (is_file($filename)) {
            unlink($filename);
        }
    }
});
