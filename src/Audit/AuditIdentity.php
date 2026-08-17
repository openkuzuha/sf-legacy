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

    /** @return array{network_token:string, client_token:string, actor_token:string}|null */
    public function tokens(string $ipAddress, string $userAgent, DateTimeImmutable $recordedAt): ?array
    {
        if ($this->key === null) {
            return null;
        }
        $binaryIp = @inet_pton($ipAddress);
        if ($binaryIp === false) {
            return null;
        }
        $period = $recordedAt->format('Y-m');

        return [
            'network_token' => $this->token('network', $period, $binaryIp),
            'client_token' => $this->token('client', $period, $userAgent),
            'actor_token' => $this->token('actor', $period, $binaryIp . "\0" . $userAgent),
        ];
    }

    private function token(string $purpose, string $period, string $value): string
    {
        $digest = hash_hmac('sha256', "bbs-audit:v1\0{$purpose}\0{$period}\0{$value}", $this->key ?? '', true);

        return rtrim(strtr(base64_encode(substr($digest, 0, 16)), '+/', '-_'), '=');
    }
}
