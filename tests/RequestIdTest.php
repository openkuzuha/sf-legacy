<?php

use App\Http\RequestIdSubscriber;
use App\Logger\RequestIdProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\RequestStack;

test('信頼したプロキシのRequest IDだけを採用してレスポンスへ返す', function () {
    $kernel = new class implements HttpKernelInterface {
        public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
        {
            return new Response();
        }
    };
    $subscriber = new RequestIdSubscriber('X-Request-ID', '127.0.0.1,10.0.0.0/8');
    $request = Request::create('/', server: [
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_X_REQUEST_ID' => 'nginx-0123456789abcdef',
    ]);
    $subscriber->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
    expect($request->attributes->get(RequestIdSubscriber::ATTRIBUTE))->toBe('nginx-0123456789abcdef');

    $response = new Response();
    $subscriber->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));
    expect($response->headers->get('X-Request-ID'))->toBe('nginx-0123456789abcdef');
});

test('Request IDをMonologレコードへ追加する', function () {
    $request = Request::create('/');
    $request->attributes->set(RequestIdSubscriber::ATTRIBUTE, str_repeat('c', 32));
    $stack = new RequestStack();
    $stack->push($request);
    $record = new LogRecord(new DateTimeImmutable(), 'app', Level::Info, 'test');

    expect((new RequestIdProcessor($stack))($record)->extra['request_id'])->toBe(str_repeat('c', 32));
});

test('非信頼接続元と不正なRequest IDは安全なIDへ置き換える', function (string $remoteAddress, string $requestId) {
    $kernel = new class implements HttpKernelInterface {
        public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
        {
            return new Response();
        }
    };
    $request = Request::create('/', server: [
        'REMOTE_ADDR' => $remoteAddress,
        'HTTP_X_REQUEST_ID' => $requestId,
    ]);
    (new RequestIdSubscriber('X-Request-ID', '127.0.0.1'))->onKernelRequest(
        new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST),
    );
    $generated = $request->attributes->get(RequestIdSubscriber::ATTRIBUTE);
    expect($generated)->toBeString()->toMatch('/^[a-f0-9]{32}$/D');
    expect($generated)->not->toBe($requestId);
})->with([
    ['203.0.113.10', 'external-0123456789abcdef'],
    ['127.0.0.1', "invalid\nlog-entry-0123456789"],
]);
