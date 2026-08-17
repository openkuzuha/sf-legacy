<?php

namespace App\Settings;

interface SiteSettingsRepository
{
    public function title(): ?string;

    public function setTitle(string $title): void;

    public function resetTitle(): void;

    public function centralPostLimit(): ?int;

    public function setCentralPostLimit(int $limit): void;

    public function resetCentralPostLimit(): void;

    public function defaultDisplayCount(): ?int;

    public function setDefaultDisplayCount(int $count): void;

    public function resetDefaultDisplayCount(): void;

    public function maxMessageLines(): ?int;

    public function setMaxMessageLines(int $lines): void;

    public function resetMaxMessageLines(): void;

    public function maxLineChars(): ?int;

    public function setMaxLineChars(int $chars): void;

    public function resetMaxLineChars(): void;

    public function maxMessageChars(): ?int;

    public function setMaxMessageChars(int $chars): void;

    public function resetMaxMessageChars(): void;

    public function visitorActiveSeconds(): ?int;

    public function setVisitorActiveSeconds(int $seconds): void;

    public function resetVisitorActiveSeconds(): void;

    public function serviceStartedAt(): ?string;

    public function setServiceStartedAt(string $date): void;

    public function resetServiceStartedAt(): void;

    public function adminName(): ?string;

    public function setAdminName(string $name): void;

    public function resetAdminName(): void;

    public function adminEmail(): ?string;

    public function setAdminEmail(string $email): void;

    public function resetAdminEmail(): void;

    /** @return list<string>|null */
    public function prohibitedWords(): ?array;

    /** @param list<string> $words */
    public function setProhibitedWords(array $words): void;

    public function resetProhibitedWords(): void;

    /** @return list<string>|null */
    public function deniedPostNetworks(): ?array;

    /** @param list<string> $networks */
    public function setDeniedPostNetworks(array $networks): void;

    public function resetDeniedPostNetworks(): void;

    /** @return list<string>|null */
    public function deniedAccessNetworks(): ?array;

    /** @param list<string> $networks */
    public function setDeniedAccessNetworks(array $networks): void;

    public function resetDeniedAccessNetworks(): void;

    public function postingEnabled(): ?bool;

    public function setPostingEnabled(bool $enabled): void;

    public function resetPostingEnabled(): void;

    public function maintenanceEnabled(): ?bool;

    public function setMaintenanceEnabled(bool $enabled): void;

    public function resetMaintenanceEnabled(): void;

    public function maintenanceMessage(): ?string;

    public function setMaintenanceMessage(string $message): void;

    public function resetMaintenanceMessage(): void;

    public function maintenanceEndsAt(): ?string;

    public function setMaintenanceEndsAt(string $endsAt): void;

    public function resetMaintenanceEndsAt(): void;

    public function undoEnabled(): ?bool;

    public function setUndoEnabled(bool $enabled): void;

    public function resetUndoEnabled(): void;

    public function undoWindowSeconds(): ?int;

    public function setUndoWindowSeconds(int $seconds): void;

    public function resetUndoWindowSeconds(): void;

    public function archiveRetentionDays(): ?int;

    public function setArchiveRetentionDays(int $days): void;

    public function resetArchiveRetentionDays(): void;

    public function archivePublicDays(): ?int;

    public function setArchivePublicDays(int $days): void;

    public function resetArchivePublicDays(): void;

    public function adminPasswordHash(): ?string;

    public function setAdminPasswordHash(string $hash): void;

    public function resetAdminPasswordHash(): void;
}
