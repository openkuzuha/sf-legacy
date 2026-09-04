<?php

namespace App\Http;

use Predis\Client;
use SessionHandler;
use SessionHandlerInterface;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\RedisSessionHandler;

final class SessionHandlerFactory
{
    public function create(bool $cloudMode, string $valkeyUrl): SessionHandlerInterface
    {
        if (!$cloudMode) {
            // PHPのini設定（session.save_handler等）どおりに動作する既定のハンドラ。
            // handler_idを明示しなかった場合とネイティブ挙動としては変わらない。
            return new SessionHandler();
        }

        return new RedisSessionHandler(new Client($valkeyUrl), ['prefix' => 'bbs:session:']);
    }
}
