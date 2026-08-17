<?php

namespace App\Logger;

use App\Http\RequestIdSubscriber;
use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsMonologProcessor]
final class RequestIdProcessor
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $requestId = $this->requestStack->getCurrentRequest()?->attributes->get(RequestIdSubscriber::ATTRIBUTE);
        if (!is_string($requestId)) {
            return $record;
        }

        return $record->with(extra: [...$record->extra, 'request_id' => $requestId]);
    }
}
