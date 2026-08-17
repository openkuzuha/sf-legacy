<?php

namespace App\Post;

use App\Settings\SiteSettings;

/** @phpstan-import-type PostRecord from PostTypes */
final class RetainedPostArchive implements PostArchive
{
    public function __construct(
        private readonly PostArchive $archive,
        private readonly SiteSettings $settings,
    ) {
    }

    public function put(array $record): bool
    {
        $stored = $this->archive->put($record);
        $this->archive->prune($this->settings->archiveRetentionDays());

        return $stored;
    }

    public function delete(array $record): bool
    {
        return $this->archive->delete($record);
    }

    public function month(string $yearMonth): array
    {
        return $this->archive->month($yearMonth);
    }

    public function entries(int $recentDays = 0): array
    {
        return $this->archive->entries($recentDays);
    }

    public function search(array $dates, string $keyword): array
    {
        return $this->archive->search($dates, $keyword);
    }

    public function prune(int $retentionDays): int
    {
        return $this->archive->prune($retentionDays);
    }

    public function clear(): int
    {
        return $this->archive->clear();
    }
}
