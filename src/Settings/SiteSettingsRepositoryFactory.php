<?php

namespace App\Settings;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SiteSettingsRepositoryFactory
{
    public function create(
        #[Autowire(param: 'app.cloud_mode')]
        bool $cloudMode,
        #[Autowire(env: 'VALKEY_URL')]
        string $valkeyUrl,
        #[Autowire(param: 'app.site_settings_filename')]
        string $filename,
    ): SiteSettingsRepository {
        return $cloudMode
            ? new ValkeySiteSettingsRepository($valkeyUrl)
            : new FileSiteSettingsRepository($filename);
    }
}
