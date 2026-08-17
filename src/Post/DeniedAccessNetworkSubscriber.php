<?php

namespace App\Post;

use App\Settings\SiteSettings;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

final class DeniedAccessNetworkSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly SiteSettings $siteSettings)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => 'onKernelController'];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        $route = $request->attributes->getString('_route');
        if (!str_starts_with($route, 'app_') || str_starts_with($route, 'app_admin')) {
            return;
        }
        $clientIp = $request->getClientIp();
        if ($clientIp === null || filter_var($clientIp, FILTER_VALIDATE_IP) === false) {
            return;
        }
        $networks = $this->siteSettings->deniedAccessNetworks();
        if ($networks !== [] && IpUtils::checkIp($clientIp, $networks)) {
            throw new AccessDeniedHttpException('このIPアドレスからは掲示板を利用できません。');
        }
    }
}
