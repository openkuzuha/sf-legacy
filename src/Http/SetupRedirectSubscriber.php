<?php

namespace App\Http;

use App\Settings\AdminPassword;
use App\Settings\CloudModeSetup;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class SetupRedirectSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AdminPassword $adminPassword,
        private readonly CloudModeSetup $cloudModeSetup,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // The route must already be resolved by RouterListener (priority 32).
        return [KernelEvents::REQUEST => ['onKernelRequest', 0]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->getString('_route');
        if (
            $route === 'app_admin_setup'
            || $route === 'app_admin_setup_mode'
            || $this->adminPassword->isConfigured()
        ) {
            return;
        }

        $token = $request->query->getString('token');
        $tokenSuffix = $token !== '' ? '?token=' . urlencode($token) : '';
        $path = $this->cloudModeSetup->isDecided() ? '/admin/setup' : '/admin/setup/mode';
        $event->setResponse(new RedirectResponse($path . $tokenSuffix, Response::HTTP_SEE_OTHER));
    }
}
