<?php

namespace App\Settings;

interface SiteSettingsRepository
{
    public function title(): ?string;

    public function setTitle(string $title): void;

    public function resetTitle(): void;

    public function adminPasswordHash(): ?string;

    public function setAdminPasswordHash(string $hash): void;

    public function resetAdminPasswordHash(): void;
}
