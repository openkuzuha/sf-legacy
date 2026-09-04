<?php

namespace App\Http;

use Predis\Client;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

final class RateLimiterStorageFactory
{
    public function __construct(
        // Symfonyの既定のレート制限用プール。ローカルモードでは、このFactoryを
        // 挟まなかった場合とまったく同じ保存先を使う（挙動を変えないため）。
        #[Autowire(service: 'cache.rate_limiter')]
        private readonly CacheItemPoolInterface $localCache,
    ) {
    }

    public function create(bool $cloudMode, string $valkeyUrl): StorageInterface
    {
        if (!$cloudMode) {
            return new CacheStorage($this->localCache);
        }

        return new CacheStorage(new RedisAdapter(new Client($valkeyUrl), 'bbs-rate-limiter'));
    }
}
