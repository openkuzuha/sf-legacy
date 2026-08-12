<?php

namespace App\PageView;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PageViewCounterFactory
{
    public function create(
        #[Autowire(env: 'POST_STORAGE')]
        string $storage,
        #[Autowire(env: 'VALKEY_URL')]
        string $valkeyUrl,
        #[Autowire(param: 'app.page_view_filename')]
        string $filename,
        #[Autowire(param: 'app.page_view_initial_value')]
        int $initialValue,
    ): PageViewCounter {
        return match ($storage) {
            'jsonl' => new FilePageViewCounter($filename, $initialValue),
            'valkey' => new ValkeyPageViewCounter($valkeyUrl, $initialValue),
            default => throw new InvalidArgumentException(sprintf('未対応のPOST_STORAGE "%s" です。', $storage)),
        };
    }
}
