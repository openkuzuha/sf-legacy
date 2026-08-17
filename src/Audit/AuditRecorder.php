<?php

namespace App\Audit;

use App\Settings\SiteSettings;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Throwable;

final class AuditRecorder
{
    public function __construct(
        private readonly SiteSettings $settings,
        private readonly AuditIdentity $identity,
        private readonly AuditLog $log,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function record(
        string $requestId,
        string $location,
        int $postId,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        $mode = $this->settings->auditMode();
        if ($mode === 'none') {
            return;
        }
        if ($ipAddress === null || $this->identity->isConfigured() === false) {
            $this->logger->error('投稿者監査情報を記録できません。', [
                'request_id' => $requestId,
                'reason' => $ipAddress === null ? 'client_ip_missing' : 'audit_hmac_key_missing',
            ]);

            return;
        }
        $recordedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $tokens = $this->identity->tokens($ipAddress, $userAgent ?? '', $recordedAt);
        if ($tokens === null) {
            $this->logger->error('投稿者監査用の識別子を生成できません。', ['request_id' => $requestId]);

            return;
        }
        $record = [
            'version' => 1,
            'recorded_at' => $recordedAt->format('Y-m-d\TH:i:s\Z'),
            'request_id' => $requestId,
            'location' => $location,
            'post_id' => $postId,
            ...$tokens,
        ];
        if ($mode === 'raw') {
            $record['ip_address'] = $ipAddress;
            $record['user_agent'] = $userAgent;
        }
        try {
            $this->log->write($record, $this->settings->auditRetentionDays());
        } catch (Throwable $exception) {
            $this->logger->error('投稿者監査情報の保存に失敗しました。', [
                'request_id' => $requestId,
                'exception' => $exception,
            ]);
        }
    }
}
