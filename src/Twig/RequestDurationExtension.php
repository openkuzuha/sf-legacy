<?php

namespace App\Twig;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class RequestDurationExtension extends AbstractExtension implements EventSubscriberInterface
{
    private float $startedAt = 0.0;

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 2048]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->startedAt = microtime(true);
        }
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('request_duration', $this->requestDuration(...))];
    }

    public function requestDuration(): string
    {
        return sprintf('%.6f', microtime(true) - $this->startedAt);
    }
}
