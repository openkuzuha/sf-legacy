<?php

namespace App\Post;

use App\Settings\SiteSettings;
use DateTimeZone;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

final class MaintenanceModeSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SiteSettings $siteSettings,
        private readonly Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => ['onKernelController', 10]];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $route = $event->getRequest()->attributes->getString('_route');
        $isPublicRoute = str_starts_with($route, 'app_') && !str_starts_with($route, 'app_admin');
        if (!$isPublicRoute || !$this->siteSettings->maintenanceEnabled()) {
            return;
        }
        $endsAt = $this->siteSettings->maintenanceEndsAt();
        $headers = [];
        if ($endsAt !== null && $endsAt->getTimestamp() > time()) {
            $headers['Retry-After'] = $endsAt->setTimezone(new DateTimeZone('GMT'))->format(DATE_RFC7231);
        }
        $event->setController(fn (): Response => new Response(
            $this->twig->render('maintenance.html.twig', [
                'app_title' => $this->siteSettings->title(),
                'message' => $this->siteSettings->maintenanceMessage(),
                'ends_at' => $endsAt,
            ]),
            Response::HTTP_SERVICE_UNAVAILABLE,
            $headers,
        ));
    }
}
