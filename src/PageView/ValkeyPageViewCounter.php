<?php

namespace App\PageView;

use Predis\Client;
use RuntimeException;

final class ValkeyPageViewCounter implements PageViewCounter
{
    private Client $client;

    public function __construct(string $url, private readonly int $initialValue)
    {
        $this->client = new Client($url);
    }

    public function increment(): int
    {
        $result = $this->client->eval(
            <<<'LUA'
            if redis.call('EXISTS', KEYS[1]) == 0 then
                redis.call('SET', KEYS[1], ARGV[1])
            end
            return redis.call('INCR', KEYS[1])
            LUA,
            1,
            'bbs:main:page-views',
            (string) $this->initialValue,
        );

        if (!is_int($result)) {
            throw new RuntimeException('Valkeyから不正なカウンター値が返されました。');
        }

        return $result;
    }
}
