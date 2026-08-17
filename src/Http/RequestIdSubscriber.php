<?php

namespace App\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class RequestIdSubscriber implements EventSubscriberInterface
{
    public const string ATTRIBUTE = '_request_id';

    /** @var list<string> */
    private readonly array $trustedProxies;

    public function __construct(
        private readonly string $headerName,
        string $trustedProxies,
    ) {
        $this->trustedProxies = array_values(array_filter(array_map('trim', explode(',', $trustedProxies))));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 2048],
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $requestId = null;
        $remoteAddress = $request->server->getString('REMOTE_ADDR');
        if ($this->trustedProxies !== [] && IpUtils::checkIp($remoteAddress, $this->trustedProxies)) {
            $candidate = $request->headers->get($this->headerName);
            if (is_string($candidate) && preg_match('/^[A-Za-z0-9._-]{16,64}$/D', $candidate) === 1) {
                $requestId = $candidate;
            }
        }
        $request->attributes->set(self::ATTRIBUTE, $requestId ?? bin2hex(random_bytes(16)));
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $requestId = $event->getRequest()->attributes->get(self::ATTRIBUTE);
        if (is_string($requestId)) {
            $event->getResponse()->headers->set($this->headerName, $requestId);
        }
    }
}
