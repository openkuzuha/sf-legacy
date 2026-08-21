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
        $hostMode = $this->settings->hostAuditMode();
        $uaMode = $this->settings->uaAuditMode();
        if ($hostMode === 'none' && $uaMode === 'none') {
            return;
        }
        if ($ipAddress === null) {
            $this->logger->error('投稿者監査情報を記録できません。', [
                'request_id' => $requestId,
                'reason' => 'client_ip_missing',
            ]);

            return;
        }

        $recordedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $record = [
            'version' => 1,
            'recorded_at' => $recordedAt->format('Y-m-d\TH:i:s\Z'),
            'request_id' => $requestId,
            'location' => $location,
            'post_id' => $postId,
        ];

        if ($hostMode === 'raw') {
            $record['ip_address'] = $ipAddress;
        } elseif ($hostMode === 'pseudonymous') {
            $networkToken = $this->identity->networkToken($ipAddress, $recordedAt);
            if ($networkToken === null) {
                $this->logger->error('投稿者監査用のホスト識別子を生成できません。', [
                    'request_id' => $requestId,
                    'reason' => $this->identity->isConfigured() ? 'token_generation_failed' : 'audit_hmac_key_missing',
                ]);
            } else {
                $record['network_token'] = $networkToken;
            }
        }

        if ($uaMode === 'raw') {
            $record['user_agent'] = $userAgent;
        } elseif ($uaMode === 'pseudonymous') {
            $clientToken = $this->identity->clientToken($userAgent ?? '', $recordedAt);
            if ($clientToken === null) {
                $this->logger->error('投稿者監査用のUA識別子を生成できません。', [
                    'request_id' => $requestId,
                    'reason' => $this->identity->isConfigured() ? 'token_generation_failed' : 'audit_hmac_key_missing',
                ]);
            } else {
                $record['client_token'] = $clientToken;
            }
        }

        $hasHostInfo = isset($record['ip_address']) || isset($record['network_token']);
        $hasUaInfo = isset($record['user_agent']) || isset($record['client_token']);
        if (!$hasHostInfo && !$hasUaInfo) {
            return;
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
