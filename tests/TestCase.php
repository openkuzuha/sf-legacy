<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;

abstract class TestCase extends WebTestCase
{
    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $server
     */
    public static function createClient(array $options = [], array $server = []): KernelBrowser
    {
        return parent::createClient($options, $server);
    }

    public static function getContainer(): Container
    {
        return parent::getContainer();
    }
}
