<?php

use App\Tests\TestCase;
use App\Settings\SiteSettings;

test('管理画面は未認証時にログインフォームを表示する', function () {
    /** @var TestCase $this */
    $client = $this->createClient();
    $client->request('GET', '/admin');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextSame('h2', '管理画面ログイン');
    $this->assertSelectorExists('form[action="/admin"][method="post"] input[name="password"][type="password"]');
    $this->assertSelectorExists('input[name="_token"]');
    $this->assertSelectorTextNotContains('main', '現在作成中です。');
});

test('環境変数のパスワードで管理画面にログインしてログアウトできる', function () {
    /** @var TestCase $this */
    $client = $this->createClient();
    $crawler = $client->request('GET', '/admin');
    $token = $crawler->filter('input[name="_token"]')->attr('value');
    $client->request('POST', '/admin', [
        'password' => 'admin-test-password',
        '_token' => $token,
    ]);

    $this->assertResponseRedirects('/admin', 303);
    $crawler = $client->followRedirect();
    $this->assertSelectorTextSame('h2', '管理画面');
    $this->assertSelectorTextContains('main', '現在作成中です。');

    $client->submit($crawler->selectButton('ログアウト')->form());
    $this->assertResponseRedirects('/admin', 303);
    $client->followRedirect();
    $this->assertSelectorTextSame('h2', '管理画面ログイン');
});

test('誤ったパスワードでは管理画面を表示しない', function () {
    /** @var TestCase $this */
    $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.2']);
    $crawler = $client->request('GET', '/admin');
    $token = $crawler->filter('input[name="_token"]')->attr('value');
    $client->request('POST', '/admin', [
        'password' => 'wrong-password',
        '_token' => $token,
    ]);

    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextSame('[role="alert"]', 'パスワードが正しくありません。');
    $this->assertSelectorTextNotContains('main', '現在作成中です。');
});

test('管理画面でサイトタイトルを保存してAPP_TITLEへ戻す', function () {
    /** @var TestCase $this */
    $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.3']);
    $settings = $this->getContainer()->get(SiteSettings::class);
    $this->assertInstanceOf(SiteSettings::class, $settings);
    $settings->resetTitle();

    try {
        $crawler = $client->request('GET', '/admin');
        $client->submit($crawler->selectButton('ログイン')->form([
            'password' => 'admin-test-password',
        ]));
        $crawler = $client->followRedirect();

        $this->assertInputValueSame('title', 'Open Kuzuha');
        $this->assertSelectorExists('input[name="title"][required][maxlength="100"]');
        $client->submit($crawler->selectButton('保存')->form([
            'title' => '管理画面で変更したタイトル',
        ]));
        $this->assertResponseRedirects('/admin', 303);
        $crawler = $client->followRedirect();
        $this->assertSelectorTextSame('[role="status"]', 'サイトタイトルを保存しました。');
        $this->assertInputValueSame('title', '管理画面で変更したタイトル');

        $client->request('GET', '/');
        $this->assertSelectorTextContains('title', '管理画面で変更したタイトル');
        $this->assertSelectorTextContains('h1', '管理画面で変更したタイトル');

        $crawler = $client->request('GET', '/admin');
        $client->submit($crawler->selectButton('APP_TITLEに戻す')->form());
        $this->assertResponseRedirects('/admin', 303);
        $crawler = $client->followRedirect();
        $this->assertSelectorTextSame('[role="status"]', 'サイトタイトルを初期値に戻しました。');
        $this->assertInputValueSame('title', 'Open Kuzuha');
    } finally {
        $settings->resetTitle();
    }
});

test('未認証または不正な入力ではサイトタイトルを変更しない', function () {
    /** @var TestCase $this */
    $client = $this->createClient([], ['REMOTE_ADDR' => '127.0.0.4']);
    $settings = $this->getContainer()->get(SiteSettings::class);
    $this->assertInstanceOf(SiteSettings::class, $settings);
    $settings->resetTitle();

    try {
        $client->request('POST', '/admin/settings/title', [
            'title' => '未認証の変更',
            '_token' => 'invalid',
        ]);
        $this->assertResponseRedirects('/admin', 303);
        expect($settings->title())->toBe('Open Kuzuha');

        $crawler = $client->request('GET', '/admin');
        $client->submit($crawler->selectButton('ログイン')->form([
            'password' => 'admin-test-password',
        ]));
        $crawler = $client->followRedirect();
        $token = $crawler->filter('form[action="/admin/settings/title"] input[name="_token"]')->attr('value');
        $client->request('POST', '/admin/settings/title', [
            'title' => str_repeat('あ', 101),
            '_token' => $token,
        ]);
        $crawler = $client->followRedirect();
        $this->assertSelectorTextSame('[role="alert"]', 'サイトタイトルは100文字以内で入力してください。');
        expect($settings->title())->toBe('Open Kuzuha');
    } finally {
        $settings->resetTitle();
    }
});
