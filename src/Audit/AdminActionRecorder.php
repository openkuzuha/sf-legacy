<?php

namespace App\Audit;

use DateTimeImmutable;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

final class AdminActionRecorder
{
    private const int RETENTION_DAYS = 180;

    public function __construct(
        #[Autowire(service: 'admin_action.audit_log')]
        private readonly AuditLog $log,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function record(string $requestId, string $action, int $postId, ?int $threadId): void
    {
        $record = [
            'version' => 1,
            'recorded_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
            'request_id' => $requestId,
            'action' => $action,
            'post_id' => $postId,
            'thread_id' => $threadId,
        ];
        try {
            $this->log->write($record, self::RETENTION_DAYS);
        } catch (Throwable $exception) {
            $this->logger->error('管理操作の監査ログ保存に失敗しました。', [
                'request_id' => $requestId,
                'action' => $action,
                'exception' => $exception,
            ]);
        }
    }
}
