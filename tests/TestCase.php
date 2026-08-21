<?php

namespace App\Tests;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

abstract class TestCase extends WebTestCase
{
    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $server
     */
    public static function createClient(array $options = [], array $server = []): KernelBrowser
    {
        $client = parent::createClient($options, $server);
        $rateLimiterCache = self::getContainer()->get('cache.rate_limiter');
        if (!$rateLimiterCache instanceof CacheItemPoolInterface) {
            throw new \LogicException('レートリミッターのキャッシュが設定されていません。');
        }
        $rateLimiterCache->clear();

        $limiter = self::getContainer()->get('limiter.post');
        if (!$limiter instanceof RateLimiterFactoryInterface) {
            throw new \LogicException('投稿レートリミッターが設定されていません。');
        }
        $clientIp = $server['REMOTE_ADDR'] ?? '127.0.0.1';
        $limiter->create(is_string($clientIp) ? $clientIp : '127.0.0.1')->reset();

        return $client;
    }

    public static function getContainer(): Container
    {
        return parent::getContainer();
    }

    public static function csrfToken(KernelBrowser $client): string
    {
        $crawler = $client->request('GET', '/');
        $token = $crawler->filter('#post-form input[name="_token"]')->attr('value');
        if (!is_string($token) || $token === '') {
            throw new \LogicException('CSRFトークンを取得できませんでした。');
        }

        return $token;
    }
}
