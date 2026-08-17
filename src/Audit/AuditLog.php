<?php

namespace App\Audit;

interface AuditLog
{
    /** @param array<string, bool|int|string|null> $record */
    public function write(array $record, int $retentionDays): void;
}
