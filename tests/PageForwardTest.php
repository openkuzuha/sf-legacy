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
