<?php

namespace App\Audit;

use DateTimeImmutable;

final class AuditIdentity
{
    private readonly ?string $key;

    public function __construct(string $hmacKey)
    {
        $decoded = base64_decode($hmacKey, true);
        $this->key = is_string($decoded) && strlen($decoded) >= 32 ? $decoded : null;
    }

    public function isConfigured(): bool
    {
        return $this->key !== null;
    }

    public function networkToken(string $ipAddress, DateTimeImmutable $recordedAt): ?string
    {
        if ($this->key === null) {
            return null;
        }
        $binaryIp = @inet_pton($ipAddress);
        if ($binaryIp === false) {
            return null;
        }

        return $this->token('network', $recordedAt->format('Y-m'), $binaryIp);
    }

    public function clientToken(string $userAgent, DateTimeImmutable $recordedAt): ?string
    {
        if ($this->key === null) {
            return null;
        }

        return $this->token('client', $recordedAt->format('Y-m'), $userAgent);
    }

    private function token(string $purpose, string $period, string $value): string
    {
        $digest = hash_hmac('sha256', "bbs-audit:v1\0{$purpose}\0{$period}\0{$value}", $this->key ?? '', true);

        return rtrim(strtr(base64_encode(substr($digest, 0, 16)), '+/', '-_'), '=');
    }
}
