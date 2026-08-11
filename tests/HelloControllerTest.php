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
    }
}
