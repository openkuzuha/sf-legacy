<?php

namespace App\Post;

use App\Settings\SiteSettings;
use Symfony\Component\HttpFoundation\IpUtils;

final class DeniedPostNetworkPolicy
{
    public function __construct(private readonly SiteSettings $siteSettings)
    {
    }

    public function denies(?string $clientIp): bool
    {
        if ($clientIp === null || filter_var($clientIp, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        $networks = $this->siteSettings->deniedPostNetworks();

        return $networks !== [] && IpUtils::checkIp($clientIp, $networks);
    }
}
