<?php

use App\Http\SessionHandlerFactory;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\RedisSessionHandler;

test('CLOUD_MODEに応じてセッションの保存先を選ぶ', function () {
    $factory = new SessionHandlerFactory();

    expect($factory->create(false, 'redis://localhost'))->toBeInstanceOf(SessionHandler::class);
    expect($factory->create(true, 'redis://localhost'))->toBeInstanceOf(RedisSessionHandler::class);
});
