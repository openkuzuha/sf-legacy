<?php

namespace App\Settings;

use Predis\Client;
use Predis\PredisException;
use RuntimeException;

final class ValkeySiteSettingsRepository implements SiteSettingsRepository
{
    private const string TITLE_KEY = 'bbs:settings:site-title';
    private const string ADMIN_PASSWORD_HASH_KEY = 'bbs:settings:admin-password-hash';
    private const string CENTRAL_POST_LIMIT_KEY = 'bbs:settings:central-post-limit';
    private const string DEFAULT_DISPLAY_COUNT_KEY = 'bbs:settings:default-display-count';
    private const string MAX_MESSAGE_LINES_KEY = 'bbs:settings:max-message-lines';
    private const string MAX_LINE_CHARS_KEY = 'bbs:settings:max-line-chars';
    private const string MAX_MESSAGE_CHARS_KEY = 'bbs:settings:max-message-chars';
    private const string VISITOR_ACTIVE_SECONDS_KEY = 'bbs:settings:visitor-active-seconds';
    private const string SERVICE_STARTED_AT_KEY = 'bbs:settings:service-started-at';
    private const string ADMIN_NAME_KEY = 'bbs:settings:admin-name';
    private const string ADMIN_EMAIL_KEY = 'bbs:settings:admin-email';
    private const string PROHIBITED_WORDS_KEY = 'bbs:settings:prohibited-words';
    private const string DENIED_POST_NETWORKS_KEY = 'bbs:settings:denied-post-networks';
    private const string DENIED_ACCESS_NETWORKS_KEY = 'bbs:settings:denied-access-networks';
    private const string POSTING_ENABLED_KEY = 'bbs:settings:posting-enabled';
    private const string MAINTENANCE_ENABLED_KEY = 'bbs:settings:maintenance-enabled';
    private const string MAINTENANCE_MESSAGE_KEY = 'bbs:settings:maintenance-message';
    private const string MAINTENANCE_ENDS_AT_KEY = 'bbs:settings:maintenance-ends-at';
    private const string AUDIT_HOST_MODE_KEY = 'bbs:settings:audit-host-mode';
    private const string AUDIT_UA_MODE_KEY = 'bbs:settings:audit-ua-mode';
    private const string AUDIT_RETENTION_DAYS_KEY = 'bbs:settings:audit-retention-days';
    private const string UNDO_ENABLED_KEY = 'bbs:settings:undo-enabled';
    private const string UNDO_WINDOW_SECONDS_KEY = 'bbs:settings:undo-window-seconds';
    private const string ARCHIVE_RETENTION_DAYS_KEY = 'bbs:settings:archive-retention-days';
    private const string ARCHIVE_PUBLIC_DAYS_KEY = 'bbs:settings:archive-public-days';

    private Client $client;

    public function __construct(string $url)
    {
        $this->client = new Client($url);
    }

    public function title(): ?string
    {
        try {
            return $this->client->get(self::TITLE_KEY);
        } catch (PredisException $exception) {
            throw new RuntimeException('Valkeyからサイトタイトルを読み込めません。', previous: $exception);
        }
    }

    public function setTitle(string $title): void
    {
        try {
            $this->client->set(self::TITLE_KEY, $title);
        } catch (PredisException $exception) {
            throw new RuntimeException('Valkeyへサイトタイトルを保存できません。', previous: $exception);
        }
    }

    public function resetTitle(): void
    {
        try {
            $this->client->del([self::TITLE_KEY]);
        } catch (PredisException $exception) {
            throw new RuntimeException('Valkeyのサイトタイトルをリセットできません。', previous: $exception);
        }
    }

    public function centralPostLimit(): ?int
    {
        try {
            $limit = $this->client->get(self::CENTRAL_POST_LIMIT_KEY);

            return is_string($limit) && ctype_digit($limit) ? (int) $limit : null;
        } catch (PredisException $exception) {
            throw new RuntimeException('Valkeyからマスターログ保存件数を読み込めません。', previous: $exception);
        }
    }

    public function setCentralPostLimit(int $limit): void
    {
        try {
            $this->client->set(self::CENTRAL_POST_LIMIT_KEY, (string) $limit);
        } catch (PredisException $exception) {
            throw new RuntimeException('Valkeyへマスターログ保存件数を保存できません。', previous: $exception);
        }
    }

    public function resetCentralPostLimit(): void
    {
        try {
            $this->client->del([self::CENTRAL_POST_LIMIT_KEY]);
        } catch (PredisException $exception) {
            throw new RuntimeException('Valkeyのマスターログ保存件数をリセットできません。', previous: $exception);
        }
    }

    public function defaultDisplayCount(): ?int
    {
        try {
            $count = $this->client->get(self::DEFAULT_DISPLAY_COUNT_KEY);

            return is_string($count) && ctype_digit($count) ? (int) $count : null;
        } catch (PredisException $exception) {
            throw new RuntimeException('Valkeyから初期表示件数を読み込めません。', previous: $exception);
        }
    }

    public function setDefaultDisplayCount(int $count): void
    {
        try {
            $this->client->set(self::DEFAULT_DISPLAY_COUNT_KEY, (string) $count);
        } catch (PredisException $exception) {
            throw new RuntimeException('Valkeyへ初期表示件数を保存できません。', previous: $exception);
        }
    }

    public function resetDefaultDisplayCount(): void
    {
        try {
            $this->client->del([self::DEFAULT_DISPLAY_COUNT_KEY]);
        } catch (PredisException $exception) {
            throw new RuntimeException('Valkeyの初期表示件数をリセットできません。', previous: $exception);
        }
    }

    public function maxMessageLines(): ?int
    {
        return $this->readInteger(self::MAX_MESSAGE_LINES_KEY, '本文の最大行数');
    }

    public function setMaxMessageLines(int $lines): void
    {
        $this->writeInteger(self::MAX_MESSAGE_LINES_KEY, $lines, '本文の最大行数');
    }

    public function resetMaxMessageLines(): void
    {
        $this->deleteKey(self::MAX_MESSAGE_LINES_KEY, '本文の最大行数');
    }

    public function maxLineChars(): ?int
    {
        return $this->readInteger(self::MAX_LINE_CHARS_KEY, '1行の最大文字数');
    }

    public function setMaxLineChars(int $chars): void
    {
        $this->writeInteger(self::MAX_LINE_CHARS_KEY, $chars, '1行の最大文字数');
    }

    public function resetMaxLineChars(): void
    {
        $this->deleteKey(self::MAX_LINE_CHARS_KEY, '1行の最大文字数');
    }

    public function maxMessageChars(): ?int
    {
        return $this->readInteger(self::MAX_MESSAGE_CHARS_KEY, '本文全体の最大文字数');
    }

    public function setMaxMessageChars(int $chars): void
    {
        $this->writeInteger(self::MAX_MESSAGE_CHARS_KEY, $chars, '本文全体の最大文字数');
    }

    public function resetMaxMessageChars(): void
    {
        $this->deleteKey(self::MAX_MESSAGE_CHARS_KEY, '本文全体の最大文字数');
    }

    public function visitorActiveSeconds(): ?int
    {
        return $this->readInteger(self::VISITOR_ACTIVE_SECONDS_KEY, '参加者カウント有効時間');
    }

    public function setVisitorActiveSeconds(int $seconds): void
    {
        $this->writeInteger(self::VISITOR_ACTIVE_SECONDS_KEY, $seconds, '参加者カウント有効時間');
    }

    public function resetVisitorActiveSeconds(): void
    {
        $this->deleteKey(self::VISITOR_ACTIVE_SECONDS_KEY, '参加者カウント有効時間');
    }

    public function serviceStartedAt(): ?string
    {
        try {
            return $this->client->get(self::SERVICE_STARTED_AT_KEY);
        } catch (PredisException $exception) {
            throw new RuntimeException('Valkeyからサービス開始日を読み込めません。', previous: $exception);
        }
    }

    public function setServiceStartedAt(string $date): void
    {
        try {
            $this->client->set(self::SERVICE_STARTED_AT_KEY, $date);
        } catch (PredisException $exception) {
            throw new RuntimeException('Valkeyへサービス開始日を保存できません。', previous: $exception);
        }
    }

    public function resetServiceStartedAt(): void
    {
        $this->deleteKey(self::SERVICE_STARTED_AT_KEY, 'サービス開始日');
    }

    public function adminName(): ?string
    {
        return $this->readString(self::ADMIN_NAME_KEY, '管理者名');
    }

    public function setAdminName(string $name): void
    {
        $this->writeString(self::ADMIN_NAME_KEY, $name, '管理者名');
    }

    public function resetAdminName(): void
    {
        $this->deleteKey(self::ADMIN_NAME_KEY, '管理者名');
    }

    public function adminEmail(): ?string
    {
        return $this->readString(self::ADMIN_EMAIL_KEY, '管理者メールアドレス');
    }

    public function setAdminEmail(string $email): void
    {
        $this->writeString(self::ADMIN_EMAIL_KEY, $email, '管理者メールアドレス');
    }

    public function resetAdminEmail(): void
    {
        $this->deleteKey(self::ADMIN_EMAIL_KEY, '管理者メールアドレス');
    }

    public function prohibitedWords(): ?array
    {
        $value = $this->readString(self::PROHIBITED_WORDS_KEY, '投稿禁止ワード');
        if ($value === null) {
            return null;
        }

        try {
            $words = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Valkeyの投稿禁止ワードが不正です。', previous: $exception);
        }
        if (!is_array($words)) {
            throw new RuntimeException('Valkeyの投稿禁止ワードが不正です。');
        }

        $normalized = [];
        foreach ($words as $word) {
            if (!is_string($word)) {
                throw new RuntimeException('Valkeyの投稿禁止ワードが不正です。');
            }
            $normalized[] = $word;
        }

        return $normalized;
    }

    public function setProhibitedWords(array $words): void
    {
        try {
            $value = json_encode($words, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('投稿禁止ワードをJSONへ変換できません。', previous: $exception);
        }
        $this->writeString(self::PROHIBITED_WORDS_KEY, $value, '投稿禁止ワード');
    }

    public function resetProhibitedWords(): void
    {
        $this->deleteKey(self::PROHIBITED_WORDS_KEY, '投稿禁止ワード');
    }

    public function deniedPostNetworks(): ?array
    {
        $value = $this->readString(self::DENIED_POST_NETWORKS_KEY, '投稿禁止IPアドレス・CIDR');
        if ($value === null) {
            return null;
        }
        try {
            $networks = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Valkeyの投稿禁止IPアドレス・CIDRが不正です。', previous: $exception);
        }
        if (!is_array($networks)) {
            throw new RuntimeException('Valkeyの投稿禁止IPアドレス・CIDRが不正です。');
        }
        foreach ($networks as $network) {
            if (!is_string($network)) {
                throw new RuntimeException('Valkeyの投稿禁止IPアドレス・CIDRが不正です。');
            }
        }

        return array_values($networks);
    }

    public function setDeniedPostNetworks(array $networks): void
    {
        try {
            $value = json_encode($networks, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('投稿禁止IPアドレス・CIDRをJSONへ変換できません。', previous: $exception);
        }
        $this->writeString(self::DENIED_POST_NETWORKS_KEY, $value, '投稿禁止IPアドレス・CIDR');
    }

    public function resetDeniedPostNetworks(): void
    {
        $this->deleteKey(self::DENIED_POST_NETWORKS_KEY, '投稿禁止IPアドレス・CIDR');
    }

    public function deniedAccessNetworks(): ?array
    {
        return $this->readNetworkList(self::DENIED_ACCESS_NETWORKS_KEY, 'アクセス禁止IPアドレス・CIDR');
    }

    public function setDeniedAccessNetworks(array $networks): void
    {
        $this->writeNetworkList(self::DENIED_ACCESS_NETWORKS_KEY, $networks, 'アクセス禁止IPアドレス・CIDR');
    }

    public function resetDeniedAccessNetworks(): void
    {
        $this->deleteKey(self::DENIED_ACCESS_NETWORKS_KEY, 'アクセス禁止IPアドレス・CIDR');
    }

    public function postingEnabled(): ?bool
    {
        return $this->readBoolean(self::POSTING_ENABLED_KEY, '投稿受付状態');
    }

    public function setPostingEnabled(bool $enabled): void
    {
        $this->writeString(self::POSTING_ENABLED_KEY, $enabled ? '1' : '0', '投稿受付状態');
    }

    public function resetPostingEnabled(): void
    {
        $this->deleteKey(self::POSTING_ENABLED_KEY, '投稿受付状態');
    }

    public function maintenanceEnabled(): ?bool
    {
        return $this->readBoolean(self::MAINTENANCE_ENABLED_KEY, 'メンテナンス状態');
    }

    public function setMaintenanceEnabled(bool $enabled): void
    {
        $this->writeString(self::MAINTENANCE_ENABLED_KEY, $enabled ? '1' : '0', 'メンテナンス状態');
    }

    public function resetMaintenanceEnabled(): void
    {
        $this->deleteKey(self::MAINTENANCE_ENABLED_KEY, 'メンテナンス状態');
    }

    public function maintenanceMessage(): ?string
    {
        return $this->readString(self::MAINTENANCE_MESSAGE_KEY, 'メンテナンス表示文');
    }

    public function setMaintenanceMessage(string $message): void
    {
        $this->writeString(self::MAINTENANCE_MESSAGE_KEY, $message, 'メンテナンス表示文');
    }

    public function resetMaintenanceMessage(): void
    {
        $this->deleteKey(self::MAINTENANCE_MESSAGE_KEY, 'メンテナンス表示文');
    }

    public function maintenanceEndsAt(): ?string
    {
        return $this->readString(self::MAINTENANCE_ENDS_AT_KEY, 'メンテナンス終了予定日時');
    }

    public function setMaintenanceEndsAt(string $endsAt): void
    {
        $this->writeString(self::MAINTENANCE_ENDS_AT_KEY, $endsAt, 'メンテナンス終了予定日時');
    }

    public function resetMaintenanceEndsAt(): void
    {
        $this->deleteKey(self::MAINTENANCE_ENDS_AT_KEY, 'メンテナンス終了予定日時');
    }

    public function hostAuditMode(): ?string
    {
        return $this->readString(self::AUDIT_HOST_MODE_KEY, '投稿者監査モード（ホスト）');
    }

    public function setHostAuditMode(string $mode): void
    {
        $this->writeString(self::AUDIT_HOST_MODE_KEY, $mode, '投稿者監査モード（ホスト）');
    }

    public function resetHostAuditMode(): void
    {
        $this->deleteKey(self::AUDIT_HOST_MODE_KEY, '投稿者監査モード（ホスト）');
    }

    public function uaAuditMode(): ?string
    {
        return $this->readString(self::AUDIT_UA_MODE_KEY, '投稿者監査モード（UA）');
    }

    public function setUaAuditMode(string $mode): void
    {
        $this->writeString(self::AUDIT_UA_MODE_KEY, $mode, '投稿者監査モード（UA）');
    }

    public function resetUaAuditMode(): void
    {
        $this->deleteKey(self::AUDIT_UA_MODE_KEY, '投稿者監査モード（UA）');
    }

    public function auditRetentionDays(): ?int
    {
        return $this->readInteger(self::AUDIT_RETENTION_DAYS_KEY, '投稿者監査情報の保存日数');
    }

    public function setAuditRetentionDays(int $days): void
    {
        $this->writeInteger(self::AUDIT_RETENTION_DAYS_KEY, $days, '投稿者監査情報の保存日数');
    }

    public function resetAuditRetentionDays(): void
    {
        $this->deleteKey(self::AUDIT_RETENTION_DAYS_KEY, '投稿者監査情報の保存日数');
    }

    public function undoEnabled(): ?bool
    {
        $value = $this->readString(self::UNDO_ENABLED_KEY, '投稿者による削除の有効状態');

        return match ($value) {
            '1' => true,
            '0' => false,
            default => null,
        };
    }

    public function setUndoEnabled(bool $enabled): void
    {
        $this->writeString(self::UNDO_ENABLED_KEY, $enabled ? '1' : '0', '投稿者による削除の有効状態');
    }

    public function resetUndoEnabled(): void
    {
        $this->deleteKey(self::UNDO_ENABLED_KEY, '投稿者による削除の有効状態');
    }

    public function undoWindowSeconds(): ?int
    {
        return $this->readInteger(self::UNDO_WINDOW_SECONDS_KEY, '投稿の削除可能時間');
    }

    public function setUndoWindowSeconds(int $seconds): void
    {
        $this->writeInteger(self::UNDO_WINDOW_SECONDS_KEY, $seconds, '投稿の削除可能時間');
    }

    public function resetUndoWindowSeconds(): void
    {
        $this->deleteKey(self::UNDO_WINDOW_SECONDS_KEY, '投稿の削除可能時間');
    }

    public function archiveRetentionDays(): ?int
    {
        return $this->readInteger(self::ARCHIVE_RETENTION_DAYS_KEY, '過去ログ保持日数');
    }

    public function setArchiveRetentionDays(int $days): void
    {
        $this->writeInteger(self::ARCHIVE_RETENTION_DAYS_KEY, $days, '過去ログ保持日数');
    }

    public function resetArchiveRetentionDays(): void
    {
        $this->deleteKey(self::ARCHIVE_RETENTION_DAYS_KEY, '過去ログ保持日数');
    }

    public function archivePublicDays(): ?int
    {
        return $this->readInteger(self::ARCHIVE_PUBLIC_DAYS_KEY, '過去ログ公開日数');
    }

    public function setArchivePublicDays(int $days): void
    {
        $this->writeInteger(self::ARCHIVE_PUBLIC_DAYS_KEY, $days, '過去ログ公開日数');
    }

    public function resetArchivePublicDays(): void
    {
        $this->deleteKey(self::ARCHIVE_PUBLIC_DAYS_KEY, '過去ログ公開日数');
    }

    public function adminPasswordHash(): ?string
    {
        try {
            return $this->client->get(self::ADMIN_PASSWORD_HASH_KEY);
        } catch (PredisException $exception) {
            throw new RuntimeException('Valkeyから管理用パスワードを読み込めません。', previous: $exception);
        }
    }

    public function setAdminPasswordHash(string $hash): void
    {
        try {
            $this->client->set(self::ADMIN_PASSWORD_HASH_KEY, $hash);
        } catch (PredisException $exception) {
            throw new RuntimeException('Valkeyへ管理用パスワードを保存できません。', previous: $exception);
        }
    }

    public function resetAdminPasswordHash(): void
    {
        try {
            $this->client->del([self::ADMIN_PASSWORD_HASH_KEY]);
        } catch (PredisException $exception) {
            throw new RuntimeException('Valkeyの管理用パスワードをリセットできません。', previous: $exception);
        }
    }

    private function readInteger(string $key, string $label): ?int
    {
        try {
            $value = $this->client->get($key);

            return is_string($value) && ctype_digit($value) ? (int) $value : null;
        } catch (PredisException $exception) {
            throw new RuntimeException(sprintf('Valkeyから%sを読み込めません。', $label), previous: $exception);
        }
    }

    private function readString(string $key, string $label): ?string
    {
        try {
            return $this->client->get($key);
        } catch (PredisException $exception) {
            throw new RuntimeException(sprintf('Valkeyから%sを読み込めません。', $label), previous: $exception);
        }
    }

    private function readBoolean(string $key, string $label): ?bool
    {
        return match ($this->readString($key, $label)) {
            '1' => true,
            '0' => false,
            default => null,
        };
    }

    private function writeString(string $key, string $value, string $label): void
    {
        try {
            $this->client->set($key, $value);
        } catch (PredisException $exception) {
            throw new RuntimeException(sprintf('Valkeyへ%sを保存できません。', $label), previous: $exception);
        }
    }

    private function writeInteger(string $key, int $value, string $label): void
    {
        try {
            $this->client->set($key, (string) $value);
        } catch (PredisException $exception) {
            throw new RuntimeException(sprintf('Valkeyへ%sを保存できません。', $label), previous: $exception);
        }
    }

    /** @return list<string>|null */
    private function readNetworkList(string $key, string $label): ?array
    {
        $value = $this->readString($key, $label);
        if ($value === null) {
            return null;
        }
        try {
            $networks = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException(sprintf('Valkeyの%sが不正です。', $label), previous: $exception);
        }
        if (!is_array($networks)) {
            throw new RuntimeException(sprintf('Valkeyの%sが不正です。', $label));
        }
        foreach ($networks as $network) {
            if (!is_string($network)) {
                throw new RuntimeException(sprintf('Valkeyの%sが不正です。', $label));
            }
        }

        return array_values($networks);
    }

    /** @param list<string> $networks */
    private function writeNetworkList(string $key, array $networks, string $label): void
    {
        try {
            $value = json_encode($networks, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException(sprintf('%sをJSONへ変換できません。', $label), previous: $exception);
        }
        $this->writeString($key, $value, $label);
    }

    private function deleteKey(string $key, string $label): void
    {
        try {
            $this->client->del([$key]);
        } catch (PredisException $exception) {
            throw new RuntimeException(sprintf('Valkeyの%sをリセットできません。', $label), previous: $exception);
        }
    }
}
