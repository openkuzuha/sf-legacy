<?php

use App\Http\RateLimiterStorageFactory;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

$wrappedPool = function (StorageInterface $storage): object {
    $property = new ReflectionProperty(CacheStorage::class, 'pool');
    $pool = $property->getValue($storage);
    assert(is_object($pool));

    return $pool;
};

test('ローカルモードでは既定のレート制限キャッシュプールをそのまま使う', function () use ($wrappedPool) {
    $localPool = new FilesystemAdapter();
    $factory = new RateLimiterStorageFactory($localPool);

    $storage = $factory->create(false, 'redis://localhost');

    expect($storage)->toBeInstanceOf(CacheStorage::class);
    expect($wrappedPool($storage))->toBe($localPool);
});

test('クラウドモードではValkeyバックエンドのプールを使う', function () use ($wrappedPool) {
    $factory = new RateLimiterStorageFactory(new FilesystemAdapter());

    $storage = $factory->create(true, 'redis://localhost');

    expect($storage)->toBeInstanceOf(CacheStorage::class);
    expect($wrappedPool($storage))->toBeInstanceOf(RedisAdapter::class);
});
