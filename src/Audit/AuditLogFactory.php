<?php

namespace App\Audit;

final class AuditLogFactory
{
    public function create(
        bool $cloudMode,
        string $valkeyUrl,
        string $directory,
        string $appTimezone,
    ): AuditLog {
        return $cloudMode
            ? new ValkeyAuditLog($valkeyUrl)
            : new FileAuditLog($directory, $appTimezone);
    }
}
