<?php

use App\Tests\TestCase;

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
});

test('存在しないスレッドのツリー表示は404を返す', function () {
    /** @var TestCase $this */
    $client = $this->createClient();
    $client->request('GET', '/tree/999999999');

    $this->assertResponseStatusCodeSame(404);
});
