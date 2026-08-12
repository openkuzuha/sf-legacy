<?php

use App\Post\JsonlPostRepository;
use App\Post\PostRecordCodec;
use App\Post\PostRepository;
use App\Tests\TestCase;
use Symfony\Component\DomCrawler\Field\FormField;

test('トップページに設定したアプリケーション名が表示される', function () {
    /** @var TestCase $this */
    $client = $this->createClient();
    $crawler = $client->request('GET', '/');
    $appTitle = $this->getContainer()->getParameter('app.title');

    $this->assertIsString($appTitle);
    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextContains('title', $appTitle);
    $this->assertSelectorTextContains('h1', $appTitle);
    $this->assertMatchesRegularExpression(
        '/^実行時間 : \\d+\\.\\d{6}秒$/',
        $crawler->filter('.request-duration')->text(),
    );
    $this->assertSelectorCount(1, 'form[action="/submit"][method="post"]');
    $this->assertSelectorCount(1, 'input[name="author"][type="text"]');
    $this->assertSelectorCount(1, 'input[name="email"][type="email"]');
    $this->assertSelectorCount(1, 'input[name="title"][type="text"]');
    $this->assertSelectorCount(1, 'textarea[name="content"]');
    $this->assertSelectorExists('input[name="auto_link"][type="checkbox"][checked]');
    $this->assertSelectorExists('.post-form-actions [data-scroll-target="page-bottom"]');
    expect($crawler->filter('.small')->text())->toMatch('/現在の参加者 : \d+人 \(300秒以内\)/');
    expect($crawler->filter('.page-view-counter')->text())->toMatch('/^2026\/08\/12 から \d+$/');
    $this->assertSelectorTextContains('#post-form .small + div', '過去ログ');
    $this->assertSelectorExists('#post-form .small + div > hr + div + hr');
    $this->assertSelectorTextContains('#post-form .archive-heading', '■ : フォロー投稿(返信)');
    $this->assertSelectorExists('#post-form .archive-heading > hr:last-child');
    $this->assertSelectorExists('#post-form .small + div + .post-form-actions');
    $this->assertSelectorExists('#page-bottom');
});

test('存在しないスレッドのツリー表示は404を返す', function () {
    /** @var TestCase $this */
    $client = $this->createClient();
    $client->request('GET', '/tree/999999999');

    $this->assertResponseStatusCodeSame(404);
});

test('返信には参照元の投稿日時へのリンクを表示する', function () {
    /** @var TestCase $this */
    $filename = sys_get_temp_dir() . '/sf-legacy-reference-' . bin2hex(random_bytes(8)) . '.jsonl';
    $repository = new JsonlPostRepository($filename, new PostRecordCodec());
    $base = [
        'thread_id' => 100,
        'location' => 'main',
        'host' => null,
        'user_agent' => null,
        'author' => '投稿者',
        'email' => '',
        'title' => '題名',
        'auto_link' => true,
    ];

    try {
        $repository->import($base + [
            'posted_at' => '2026-08-12T05:30:47Z',
            'post_id' => 100,
            'message' => '元の投稿',
            'reply_to' => null,
        ]);
        $repository->import($base + [
            'posted_at' => '2026-08-12T05:31:00Z',
            'post_id' => 101,
            'message' => "返信\r\r",
            'reply_to' => 100,
        ]);

        $client = $this->createClient();
        $this->getContainer()->set(PostRepository::class, $repository);
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $actions = $client->getCrawler()->filter('#m101 .nw > a');
        expect($actions->eq(0)->text())->toBe('■')
            ->and($actions->eq(1)->text())->toBe('◆')
            ->and($actions->eq(2)->text())->toBe('木')
            ->and($actions->eq(1)->attr('href'))->toBe('/thread/100')
            ->and($actions->eq(2)->attr('href'))->toBe('/tree/100');
        $this->assertSelectorTextContains(
            '#m101 a[href="/tree/100#m100"]',
            '参考：2026/08/12(水) 14:30:47',
        );
        $responseHtml = $client->getResponse()->getContent();
        expect($responseHtml)->toBeString()
            ->and($responseHtml)->toContain("返信\n\n<a href=\"/tree/100#m100\"")
            ->and($responseHtml)->not->toContain("返信\n\n\n<a href=\"/tree/100#m100\"");
        $this->assertSelectorCount(0, '#m100 blockquote a');
    } finally {
        if (is_file($filename)) {
            unlink($filename);
        }
    }
});

test('■から引用付きReplyを投稿する', function () {
    /** @var TestCase $this */
    $filename = sys_get_temp_dir() . '/sf-legacy-reply-' . bin2hex(random_bytes(8)) . '.jsonl';
    $repository = new JsonlPostRepository($filename, new PostRecordCodec());

    try {
        $repository->import([
            'posted_at' => '2026-08-12T05:30:47Z',
            'post_id' => 100,
            'thread_id' => 100,
            'location' => 'main',
            'host' => null,
            'user_agent' => null,
            'author' => '元投稿者',
            'email' => '',
            'title' => '元の題名',
            'message' => "一行目\n二行目",
            'auto_link' => true,
            'reply_to' => null,
        ]);

        $client = $this->createClient();
        $client->disableReboot();
        $this->getContainer()->set(PostRepository::class, $repository);
        $client->request('GET', '/');
        $this->assertSelectorExists('#m100 a[title="Reply"][href="/reply/100"]');

        $crawler = $client->request('GET', '/reply/100');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextSame('title', 'Open Kuzuha');
        $this->assertSelectorTextContains('#m100', '一行目');
        $this->assertSelectorTextContains('p', 'Reply記事投稿');
        $this->assertSelectorExists('#post-form input[name="reply_to"][value="100"]');
        $this->assertInputValueSame('title', '＞元投稿者');
        $form = $crawler->selectButton('投稿／リロード')->form();
        $contentField = $form->get('content');
        assert($contentField instanceof FormField);
        expect($contentField->getValue())->toBe("> 一行目\n> 二行目\n\n");

        $client->submitForm('投稿／リロード', [
            'author' => '返信者',
            'content' => "> 一行目\n> 二行目\n\n返信本文",
            'auto_link' => false,
        ]);
        $this->assertResponseRedirects('/');
        expect($client->getCookieJar()->get('bbs_display_count')?->getValue())->toBe('40')
            ->and($client->getCookieJar()->get('bbs_auto_link')?->getValue())->toBe('0');

        $reply = $repository->all()[0];
        expect($reply['thread_id'])->toBe(100)
            ->and($reply['reply_to'])->toBe(100)
            ->and($reply['auto_link'])->toBeFalse()
            ->and($reply['message'])->toEndWith('返信本文');
    } finally {
        if (is_file($filename)) {
            unlink($filename);
        }
    }
});
