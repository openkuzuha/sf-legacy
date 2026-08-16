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
    $this->assertSelectorExists('html[style*="--bbs-background: #004040"]');
    $this->assertSelectorExists('html[style*="--bbs-quote: #cccccc"]');
    $this->assertMatchesRegularExpression(
        '/^実行時間 : \\d+\\.\\d{6}秒$/',
        $crawler->filter('.request-duration')->text(),
    );
    $this->assertSelectorCount(1, 'form[action="/submit"][method="post"]');
    $this->assertSelectorCount(1, '#post-form input[type="hidden"][name="_token"]');
    $this->assertSelectorCount(1, '.post-honeypot[aria-hidden="true"] input[name="website"][tabindex="-1"]');
    $this->assertSelectorCount(1, 'input[name="author"][type="text"]');
    $this->assertSelectorCount(1, 'input[name="email"][type="email"]');
    $this->assertSelectorCount(1, 'input[name="title"][type="text"]');
    $this->assertSelectorCount(1, 'textarea[name="content"]');
    $this->assertSelectorExists('input[name="author"][data-character-limit="30"]');
    $this->assertSelectorExists('input[name="email"][data-character-limit="255"]');
    $this->assertSelectorExists('input[name="title"][data-character-limit="40"]');
    $this->assertSelectorExists(
        'textarea[name="content"][data-message-line-limit="50"][data-line-char-limit="200"]',
    );
    $this->assertSelectorCount(4, '#post-form output.post-input-limit');
    $this->assertSelectorExists('input[name="auto_link"][type="checkbox"][checked]');
    $this->assertSelectorTextSame(
        '.post-settings a.settings-button[href="/personal-settings"][role="button"]',
        '個人用環境設定',
    );
    $this->assertSelectorExists('.post-form-actions [data-scroll-target="page-bottom"]');
    $this->assertSelectorTextSame('.post-form-actions button[form="unread-form"]', '未読');
    $this->assertSelectorExists('#unread-form input[name="p"]');
    expect($crawler->filter('.small')->text())->toMatch('/現在の参加者 : \d+人 \(300秒以内\)/');
    $this->assertSelectorTextContains('#post-form .small', '最大記事件数 : 500件');
    expect($crawler->filter('.page-view-counter')->text())->toMatch('/^2026\/08\/12 から \d+$/');
    $this->assertSelectorTextContains('#post-form .small + div', '過去ログ');
    $this->assertSelectorExists('#post-form .archive-heading a[href="/archive"]');
    $this->assertSelectorTextSame(
        '#post-form .archive-heading a.additional-link[href="https://github.com/openkuzuha"]',
        'OpenKuzuha',
    );
    $this->assertSelectorCount(1, '#post-form .archive-heading a.additional-link');
    expect($crawler->filter('#post-form .lower-links')->text(null, true))
        ->toBe('広報室 | 過去ログ | OpenKuzuha');
    $this->assertSelectorExists('.page-header a[href^="/tree?p="]');
    $this->assertSelectorExists('#post-form .small + div > hr + div + hr');
    $this->assertSelectorTextContains(
        '#post-form .archive-heading',
        '■ : フォロー投稿(返信)　★ : 投稿者検索　◆ : スレッド表示　木 : ツリー表示',
    );
    $this->assertSelectorExists('#post-form .archive-heading > hr:last-child');
    $this->assertSelectorExists('#post-form .small + div + .post-form-actions');
    $this->assertSelectorExists('#page-bottom');
});

test('個人用環境設定に色設定UIを表示する', function () {
    /** @var TestCase $this */
    $client = $this->createClient();
    $client->request('GET', '/personal-settings');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextSame('h2', '個人用環境設定');
    $this->assertSelectorCount(8, '.color-settings input[type="text"][minlength="3"][maxlength="6"][pattern]');
    $this->assertInputValueSame('C_TEXT', 'ffffff');
    $this->assertInputValueSame('C_BACKGROUND', '004040');
    $this->assertInputValueSame('C_A_COLOR', 'eeffee');
    $this->assertInputValueSame('C_A_VISITED', 'dddddd');
    $this->assertInputValueSame('C_A_ACTIVE', 'ff0000');
    $this->assertInputValueSame('C_A_HOVER', '10e0e0');
    $this->assertInputValueSame('C_SUBJ', 'fffffe');
    $this->assertInputValueSame('C_QMSG', 'cccccc');
    $this->assertSelectorTextSame('.personal-settings-form button[type="submit"]', '登録');
    $this->assertSelectorTextSame('.personal-settings-form button[data-reset-colors]', '初期設定に戻す');
});

test('自分の直前の投稿だけを×ボタンから削除する', function () {
    /** @var TestCase $this */
    $client = $this->createClient();
    $suffix = bin2hex(random_bytes(8));
    $keptMessage = '残る投稿-' . $suffix;
    $deletedMessage = '消す投稿-' . $suffix;
    $token = $this->csrfToken($client);
    $client->request('POST', '/submit', ['title' => '一件目', 'content' => $keptMessage, '_token' => $token]);
    $this->assertResponseRedirects();
    $client->request('POST', '/submit', ['title' => '二件目', 'content' => $deletedMessage, '_token' => $token]);
    $this->assertResponseRedirects();
    $crawler = $client->followRedirect();

    $undoForm = $crawler->filter('.m form[action$="/undo"][method="post"]');
    $this->assertCount(1, $undoForm);
    expect($undoForm->ancestors()->filter('.m')->text())->toContain($deletedMessage);
    $client->submit($undoForm->form());
    $crawler = $client->followRedirect();

    $this->assertSelectorTextContains('main', $keptMessage);
    $this->assertSelectorTextNotContains('main', $deletedMessage);
    $this->assertSelectorCount(0, 'form[action$="/undo"]');
});

test('全ツリー表示ページを表示する', function () {
    /** @var TestCase $this */
    $client = $this->createClient();
    $client->request('GET', '/tree');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorExists('main');
    $this->assertSelectorTextNotContains('main', '全ツリー表示は現在作成中です。');
    $this->assertSelectorExists('form#post-form[action="/submit"][method="post"]');
    $this->assertSelectorTextSame('.page-header a.header-link[href="/"]', '標準画面');
    $this->assertSelectorCount(0, '.page-header a.header-link[href="/tree"]');
    $this->assertSelectorCount(0, 'main > hr:last-child');
});

test('過去ログページは一覧見出しを表示する', function () {
    /** @var TestCase $this */
    $client = $this->createClient();
    $client->request('GET', '/archive');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextSame('h2', '過去ログ一覧');
    $this->assertSelectorExists('form[action="/archive"][method="get"]');
    $this->assertSelectorExists('input[name="keyword"]');
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
        $actions = $client->getCrawler()->filter('#m101 .nw .nb > a');
        expect($actions->eq(0)->text())->toBe('■')
            ->and($actions->eq(1)->text())->toBe('★')
            ->and($actions->eq(2)->text())->toBe('◆')
            ->and($actions->eq(3)->text())->toBe('木')
            ->and($actions->eq(1)->attr('href'))->toBe('/author?name=%E6%8A%95%E7%A8%BF%E8%80%85')
            ->and($actions->eq(2)->attr('href'))->toBe('/thread/100')
            ->and($actions->eq(3)->attr('href'))->toBe('/tree/100?p=101');
        $this->assertSelectorTextContains(
            '#m101 a[href="/reply/100"]',
            '参考：2026/08/12(水) 14:30:47',
        );
        $responseHtml = $client->getResponse()->getContent();
        expect($responseHtml)->toBeString()
            ->and($responseHtml)->toContain("返信\n\n<a href=\"/reply/100\"")
            ->and($responseHtml)->not->toContain("返信\n\n\n<a href=\"/reply/100\"");
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
        $this->assertSelectorCount(0, '.page-header');
        $this->assertSelectorTextContains('#m100', '一行目');
        $actions = $crawler->filter('#m100 .nw .nb > a');
        expect($actions->eq(0)->text())->toBe('■')
            ->and($actions->eq(0)->attr('href'))->toBe('/reply/100')
            ->and($actions->eq(1)->text())->toBe('★')
            ->and($actions->eq(1)->attr('href'))->toBe('/author?name=%E5%85%83%E6%8A%95%E7%A8%BF%E8%80%85')
            ->and($actions->eq(2)->text())->toBe('◆')
            ->and($actions->eq(2)->attr('href'))->toBe('/thread/100')
            ->and($actions->eq(3)->text())->toBe('木')
            ->and($actions->eq(3)->attr('href'))->toBe('/tree/100');
        $this->assertSelectorTextSame('.follow-heading', 'フォロー記事投稿(返信)　←戻る');
        $this->assertSelectorTextSame('footer a[href="#page-top"]', '▲');
        $this->assertSelectorExists('#post-form input[name="reply_to"][value="100"]');
        $this->assertSelectorCount(0, '#post-form input[name="display_count"]');
        $this->assertSelectorExists('#post-form input[name="auto_link"]');
        $this->assertSelectorCount(0, '#post-form a.settings-button');
        $this->assertSelectorCount(0, '#post-form > .post-form-actions');
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
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextSame('.page-title a[href="/"]', '書き込み完了');
        $this->assertSelectorCount(0, '.page-header');
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

test('投稿者名がある記事だけ★から投稿者検索できる', function () {
    /** @var TestCase $this */
    $filename = sys_get_temp_dir() . '/sf-legacy-author-' . bin2hex(random_bytes(8)) . '.jsonl';
    $repository = new JsonlPostRepository($filename, new PostRecordCodec());
    $base = [
        'thread_id' => 100,
        'location' => 'main',
        'host' => null,
        'user_agent' => null,
        'email' => '',
        'title' => '題名',
        'message' => '本文',
        'auto_link' => true,
        'reply_to' => null,
    ];

    try {
        $repository->import($base + ['posted_at' => '2026-08-12T05:30:00Z', 'post_id' => 100, 'author' => '名前']);
        $repository->import($base + ['posted_at' => '2026-08-12T05:31:00Z', 'post_id' => 101, 'author' => '']);
        $repository->import($base + ['posted_at' => '2026-08-12T05:32:00Z', 'post_id' => 102, 'author' => '別人']);
        $repository->import($base + ['posted_at' => '2026-08-12T05:33:00Z', 'post_id' => 103, 'author' => '名前']);

        $client = $this->createClient();
        $client->disableReboot();
        $this->getContainer()->set(PostRepository::class, $repository);
        $client->request('GET', '/');

        $namedActions = $client->getCrawler()->filter('#m103 .nw .nb > a');
        expect($namedActions->eq(0)->text())->toBe('■')
            ->and($namedActions->eq(1)->text())->toBe('★')
            ->and($namedActions->eq(2)->text())->toBe('◆')
            ->and($namedActions->eq(3)->text())->toBe('木')
            ->and($namedActions->eq(1)->attr('href'))->toBe('/author?name=%E5%90%8D%E5%89%8D');
        $this->assertSelectorCount(0, '#m101 a[title="投稿者検索"]');

        $client->request('GET', '/author?name=' . rawurlencode('名前'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.author-search-heading', '投稿者検索：名前');
        $this->assertSelectorCount(2, 'main .m');
        $this->assertSelectorCount(0, '#m102');
        $this->assertSelectorTextContains('.author-search-summary', '2件見つかりました。');
    } finally {
        if (is_file($filename)) {
            unlink($filename);
        }
    }
});

test('ハニーポット項目が入力された投稿を無視する', function () {
    /** @var TestCase $this */
    $client = $this->createClient();
    $token = $this->csrfToken($client);
    $content = 'ハニーポットテスト' . bin2hex(random_bytes(8));
    $client->request('POST', '/submit', [
        'content' => $content,
        'website' => 'http://spam.example/',
        '_token' => $token,
    ]);

    $this->assertResponseRedirects();
    $client->request('GET', '/');
    $this->assertSelectorTextNotContains('main', $content);
});

test('CSRFトークンが不正な投稿を拒否する', function () {
    /** @var TestCase $this */
    $client = $this->createClient();
    $client->request('POST', '/submit', ['content' => 'CSRFテスト1']);
    $this->assertResponseStatusCodeSame(400);

    $client->request('POST', '/submit', ['content' => 'CSRFテスト2', '_token' => '不正なトークン']);
    $this->assertResponseStatusCodeSame(400);
});

test('短時間に投稿できる件数をIPアドレス単位で制限する', function () {
    /** @var TestCase $this */
    $client = $this->createClient();
    $token = $this->csrfToken($client);
    $client->request('POST', '/submit', ['content' => '制限テスト1', '_token' => $token]);
    $this->assertResponseRedirects();
    $client->request('POST', '/submit', ['content' => '制限テスト2', '_token' => $token]);
    $this->assertResponseRedirects();
    $client->request('POST', '/submit', ['content' => '制限テスト3', '_token' => $token]);

    $this->assertResponseStatusCodeSame(429);
    expect($client->getResponse()->headers->get('Retry-After'))->not->toBeNull();
});

test('同一IPからの同一内容の投稿を数分間拒否する', function () {
    /** @var TestCase $this */
    $client = $this->createClient();
    $token = $this->csrfToken($client);
    $content = '重複テスト' . bin2hex(random_bytes(8));
    $client->request('POST', '/submit', ['content' => $content, '_token' => $token]);
    $this->assertResponseRedirects();
    $client->request('POST', '/submit', ['content' => $content, '_token' => $token]);

    $this->assertResponseStatusCodeSame(429);
    expect($client->getResponse()->headers->get('Retry-After'))->not->toBeNull();
});

test('legacy互換の入力上限を超えた投稿を拒否する', function () {
    /** @var TestCase $this */
    $client = $this->createClient();
    $token = $this->csrfToken($client);
    $invalidPosts = [
        ['author' => str_repeat('あ', 31), 'content' => '本文'],
        ['email' => str_repeat('a', 256), 'content' => '本文'],
        ['title' => str_repeat('あ', 41), 'content' => '本文'],
        ['content' => implode("\n", array_fill(0, 51, '行'))],
        ['content' => str_repeat('あ', 201)],
    ];

    foreach ($invalidPosts as $post) {
        $client->request('POST', '/submit', $post + ['_token' => $token]);
        $this->assertResponseStatusCodeSame(400);
    }
});
