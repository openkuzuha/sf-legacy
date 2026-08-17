<?php

namespace App\Settings;

interface SiteSettingsRepository
{
    public function title(): ?string;

    public function setTitle(string $title): void;

    public function resetTitle(): void;
}
