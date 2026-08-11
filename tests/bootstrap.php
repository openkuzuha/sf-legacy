<?php

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Symfony\Component\Dotenv\Dotenv::class, 'bootEnv')) {
    (new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}
