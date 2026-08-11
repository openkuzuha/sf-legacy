<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HelloControllerTest extends WebTestCase
{
    public function testHelloWorldUsesConfiguredApplicationTitle(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Hello World!');
        self::assertSelectorTextContains('title', 'sf-legacy');
        self::assertSelectorTextContains('p', 'sf-legacy');
        self::assertSelectorCount(1, 'form[action="/submit"][method="post"]');
        self::assertSelectorCount(1, 'textarea[name="content"]');
    }

    public function testSubmitDumpsTheRequest(): void
    {
        $client = static::createClient();
        $client->request('POST', '/submit', ['content' => 'dump me']);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Request', $client->getResponse()->getContent());
        self::assertStringContainsString('dump me', $client->getResponse()->getContent());
    }
}
