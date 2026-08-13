<?php

namespace App\PageView;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PageViewCounterFactory
{
    public function create(
        #[Autowire(param: 'app.cloud_mode')]
        bool $cloudMode,
        #[Autowire(env: 'VALKEY_URL')]
        string $valkeyUrl,
        #[Autowire(param: 'app.page_view_filename')]
        string $filename,
        #[Autowire(param: 'app.page_view_initial_value')]
        int $initialValue,
    ): PageViewCounter {
        return $cloudMode
            ? new ValkeyPageViewCounter($valkeyUrl, $initialValue)
            : new FilePageViewCounter($filename, $initialValue);
    }
}
