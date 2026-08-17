<?php

namespace App\Settings;

use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class SiteSettings
{
    public const int MAX_TITLE_CHARS = 100;
    public const int MIN_CENTRAL_POST_LIMIT = 1;
    public const int MAX_CENTRAL_POST_LIMIT = 100000;
    public const int MIN_DEFAULT_DISPLAY_COUNT = 1;
    public const int MAX_DEFAULT_DISPLAY_COUNT = 1000;
    public const int MIN_MESSAGE_LIMIT = 1;
    public const int MAX_MESSAGE_LIMIT = 10000;
    public const int MIN_VISITOR_ACTIVE_SECONDS = 1;
    public const int MAX_VISITOR_ACTIVE_SECONDS = 86400;
    public const int MAX_ADMIN_NAME_CHARS = 30;
    public const int MAX_ADMIN_EMAIL_CHARS = 255;
    public const int MAX_PROHIBITED_WORDS = 100;
    public const int MAX_PROHIBITED_WORD_CHARS = 100;
    public const int MIN_UNDO_WINDOW_SECONDS = 1;
    public const int MAX_UNDO_WINDOW_SECONDS = 2592000;
    public const int MIN_ARCHIVE_RETENTION_DAYS = 0;
    public const int MAX_ARCHIVE_RETENTION_DAYS = 3650;
    public const int MIN_ARCHIVE_PUBLIC_DAYS = 1;
    public const int MAX_ARCHIVE_PUBLIC_DAYS = 3650;

    public function __construct(
        private readonly SiteSettingsRepository $repository,
        private readonly LoggerInterface $logger,
        private readonly string $defaultTitle,
        private readonly int $defaultCentralPostLimit,
        private readonly int $defaultDisplayCount = 40,
        private readonly int $defaultMaxMessageLines = 50,
        private readonly int $defaultMaxLineChars = 200,
        private readonly int $defaultMaxMessageChars = 8400,
        private readonly int $defaultVisitorActiveSeconds = 300,
        private readonly string $defaultServiceStartedAt = '2026-08-12',
        private readonly string $defaultAdminName = '管理人',
        private readonly string $defaultAdminEmail = '',
        private readonly bool $defaultUndoEnabled = true,
        private readonly int $defaultUndoWindowSeconds = 86400,
        private readonly int $defaultArchiveRetentionDays = 0,
        private readonly int $defaultArchivePublicDays = 30,
    ) {
    }

    public function archivePublicDays(): int
    {
        try {
            $days = $this->repository->archivePublicDays() ?? $this->defaultArchivePublicDays;
            $this->validateArchivePublicDays($days);

            return $days;
        } catch (RuntimeException | InvalidArgumentException $exception) {
            $this->logger->warning('過去ログ公開日数を読み込めないため、初期値を使用します。', [
                'exception' => $exception,
            ]);

            return $this->defaultArchivePublicDays;
        }
    }

    public function defaultArchivePublicDays(): int
    {
        return $this->defaultArchivePublicDays;
    }

    public function setArchivePublicDays(int $days): void
    {
        $this->validateArchivePublicDays($days);
        $this->repository->setArchivePublicDays($days);
    }

    public function resetArchivePublicDays(): void
    {
        $this->repository->resetArchivePublicDays();
    }

    public function archiveRetentionDays(): int
    {
        try {
            $days = $this->repository->archiveRetentionDays() ?? $this->defaultArchiveRetentionDays;
            $this->validateArchiveRetentionDays($days);

            return $days;
        } catch (RuntimeException | InvalidArgumentException $exception) {
            $this->logger->warning('過去ログ保持日数を読み込めないため、初期値を使用します。', [
                'exception' => $exception,
            ]);

            return $this->defaultArchiveRetentionDays;
        }
    }

    public function defaultArchiveRetentionDays(): int
    {
        return $this->defaultArchiveRetentionDays;
    }

    public function setArchiveRetentionDays(int $days): void
    {
        $this->validateArchiveRetentionDays($days);
        $this->repository->setArchiveRetentionDays($days);
    }

    public function resetArchiveRetentionDays(): void
    {
        $this->repository->resetArchiveRetentionDays();
    }

    public function undoEnabled(): bool
    {
        try {
            return $this->repository->undoEnabled() ?? $this->defaultUndoEnabled;
        } catch (RuntimeException $exception) {
            $this->logger->warning('投稿者による削除の設定を読み込めないため、初期値を使用します。', [
                'exception' => $exception,
            ]);

            return $this->defaultUndoEnabled;
        }
    }

    public function defaultUndoEnabled(): bool
    {
        return $this->defaultUndoEnabled;
    }

    public function undoWindowSeconds(): int
    {
        try {
            $seconds = $this->repository->undoWindowSeconds() ?? $this->defaultUndoWindowSeconds;
            $this->validateUndoWindowSeconds($seconds);

            return $seconds;
        } catch (RuntimeException | InvalidArgumentException $exception) {
            $this->logger->warning('投稿の削除可能時間を読み込めないため、初期値を使用します。', [
                'exception' => $exception,
            ]);

            return $this->defaultUndoWindowSeconds;
        }
    }

    public function defaultUndoWindowSeconds(): int
    {
        return $this->defaultUndoWindowSeconds;
    }

    public function setUndoSettings(bool $enabled, int $seconds): void
    {
        $this->validateUndoWindowSeconds($seconds);
        $this->repository->setUndoEnabled($enabled);
        $this->repository->setUndoWindowSeconds($seconds);
    }

    public function resetUndoSettings(): void
    {
        $this->repository->resetUndoEnabled();
        $this->repository->resetUndoWindowSeconds();
    }

    /** @return list<string> */
    public function prohibitedWords(): array
    {
        try {
            $words = $this->repository->prohibitedWords() ?? [];
            $this->validateProhibitedWords($words);

            return $words;
        } catch (RuntimeException | InvalidArgumentException $exception) {
            $this->logger->warning('投稿禁止ワードを読み込めないため、初期値を使用します。', [
                'exception' => $exception,
            ]);

            return [];
        }
    }

    public function prohibitedWordsText(): string
    {
        return implode("\n", $this->prohibitedWords());
    }

    public function setProhibitedWordsText(string $text): void
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            throw new InvalidArgumentException('投稿禁止ワードをUTF-8で入力してください。');
        }
        $lines = preg_split('/\R/u', $text);
        if ($lines === false) {
            throw new InvalidArgumentException('投稿禁止ワードを読み取れません。');
        }
        $words = [];
        foreach ($lines as $line) {
            $word = trim($line);
            if ($word !== '' && !in_array($word, $words, true)) {
                $words[] = $word;
            }
        }
        $this->validateProhibitedWords($words);
        $this->repository->setProhibitedWords($words);
    }

    public function resetProhibitedWords(): void
    {
        $this->repository->resetProhibitedWords();
    }

    public function adminName(): string
    {
        return $this->readStringSetting(
            $this->repository->adminName(...),
            $this->defaultAdminName,
            '管理者名',
            $this->validateAdminName(...),
        );
    }

    public function defaultAdminName(): string
    {
        return $this->defaultAdminName;
    }

    public function setAdminName(string $name): void
    {
        $name = trim($name);
        $this->validateAdminName($name);
        $this->repository->setAdminName($name);
    }

    public function resetAdminName(): void
    {
        $this->repository->resetAdminName();
    }

    public function adminEmail(): string
    {
        return $this->readStringSetting(
            $this->repository->adminEmail(...),
            $this->defaultAdminEmail,
            '管理者メールアドレス',
            $this->validateAdminEmail(...),
        );
    }

    public function defaultAdminEmail(): string
    {
        return $this->defaultAdminEmail;
    }

    public function setAdminEmail(string $email): void
    {
        $email = trim($email);
        $this->validateAdminEmail($email);
        $this->repository->setAdminEmail($email);
    }

    public function resetAdminEmail(): void
    {
        $this->repository->resetAdminEmail();
    }

    public function setAdminIdentity(string $name, string $email): void
    {
        $name = trim($name);
        $email = trim($email);
        $this->validateAdminName($name);
        $this->validateAdminEmail($email);
        $this->repository->setAdminName($name);
        $this->repository->setAdminEmail($email);
    }

    public function serviceStartedAt(): string
    {
        try {
            $date = $this->repository->serviceStartedAt() ?? $this->defaultServiceStartedAt;
            $this->validateServiceStartedAt($date);

            return $date;
        } catch (RuntimeException | InvalidArgumentException $exception) {
            $this->logger->warning('サービス開始日を読み込めないため、既定値を使用します。', [
                'exception' => $exception,
            ]);

            return $this->defaultServiceStartedAt;
        }
    }

    public function formattedServiceStartedAt(): string
    {
        return str_replace('-', '/', $this->serviceStartedAt());
    }

    public function defaultServiceStartedAt(): string
    {
        return $this->defaultServiceStartedAt;
    }

    public function setServiceStartedAt(string $date): void
    {
        $this->validateServiceStartedAt($date);
        $this->repository->setServiceStartedAt($date);
    }

    public function resetServiceStartedAt(): void
    {
        $this->repository->resetServiceStartedAt();
    }

    public function visitorActiveSeconds(): int
    {
        try {
            $seconds = $this->repository->visitorActiveSeconds();
            if ($seconds === null) {
                return $this->defaultVisitorActiveSeconds;
            }
            $this->validateVisitorActiveSeconds($seconds);

            return $seconds;
        } catch (RuntimeException | InvalidArgumentException $exception) {
            $this->logger->warning('参加者カウント有効時間を読み込めないため、既定値を使用します。', [
                'exception' => $exception,
            ]);

            return $this->defaultVisitorActiveSeconds;
        }
    }

    public function defaultVisitorActiveSeconds(): int
    {
        return $this->defaultVisitorActiveSeconds;
    }

    public function setVisitorActiveSeconds(int $seconds): void
    {
        $this->validateVisitorActiveSeconds($seconds);
        $this->repository->setVisitorActiveSeconds($seconds);
    }

    public function resetVisitorActiveSeconds(): void
    {
        $this->repository->resetVisitorActiveSeconds();
    }

    public function maxMessageLines(): int
    {
        return $this->readMessageLimit(
            $this->repository->maxMessageLines(...),
            $this->defaultMaxMessageLines,
            '本文の最大行数',
        );
    }

    public function defaultMaxMessageLines(): int
    {
        return $this->defaultMaxMessageLines;
    }

    public function setMaxMessageLines(int $lines): void
    {
        $this->validateMessageLimit($lines, '本文の最大行数');
        $this->repository->setMaxMessageLines($lines);
    }

    public function resetMaxMessageLines(): void
    {
        $this->repository->resetMaxMessageLines();
    }

    public function maxLineChars(): int
    {
        return $this->readMessageLimit(
            $this->repository->maxLineChars(...),
            $this->defaultMaxLineChars,
            '1行の最大文字数',
        );
    }

    public function defaultMaxLineChars(): int
    {
        return $this->defaultMaxLineChars;
    }

    public function setMaxLineChars(int $chars): void
    {
        $this->validateMessageLimit($chars, '1行の最大文字数');
        $this->repository->setMaxLineChars($chars);
    }

    public function resetMaxLineChars(): void
    {
        $this->repository->resetMaxLineChars();
    }

    public function maxMessageChars(): int
    {
        return $this->readMessageLimit(
            $this->repository->maxMessageChars(...),
            $this->defaultMaxMessageChars,
            '本文全体の最大文字数',
        );
    }

    public function defaultMaxMessageChars(): int
    {
        return $this->defaultMaxMessageChars;
    }

    public function setMaxMessageChars(int $chars): void
    {
        $this->validateMessageLimit($chars, '本文全体の最大文字数');
        $this->repository->setMaxMessageChars($chars);
    }

    public function resetMaxMessageChars(): void
    {
        $this->repository->resetMaxMessageChars();
    }

    public function defaultDisplayCount(): int
    {
        try {
            $count = $this->repository->defaultDisplayCount();
            if ($count === null) {
                return $this->defaultDisplayCount;
            }
            if ($count < self::MIN_DEFAULT_DISPLAY_COUNT || $count > self::MAX_DEFAULT_DISPLAY_COUNT) {
                throw new RuntimeException('保存された初期表示件数が範囲外です。');
            }

            return $count;
        } catch (RuntimeException $exception) {
            $this->logger->warning('初期表示件数を読み込めないため、既定値を使用します。', ['exception' => $exception]);

            return $this->defaultDisplayCount;
        }
    }

    public function configuredDefaultDisplayCount(): int
    {
        return $this->defaultDisplayCount;
    }

    public function setDefaultDisplayCount(int $count): void
    {
        if ($count < self::MIN_DEFAULT_DISPLAY_COUNT || $count > self::MAX_DEFAULT_DISPLAY_COUNT) {
            throw new InvalidArgumentException(sprintf(
                '初期表示件数は%d件以上%d件以下で入力してください。',
                self::MIN_DEFAULT_DISPLAY_COUNT,
                self::MAX_DEFAULT_DISPLAY_COUNT,
            ));
        }
        $this->repository->setDefaultDisplayCount($count);
    }

    public function resetDefaultDisplayCount(): void
    {
        $this->repository->resetDefaultDisplayCount();
    }

    public function centralPostLimit(): int
    {
        try {
            $limit = $this->repository->centralPostLimit();
            if ($limit === null) {
                return $this->defaultCentralPostLimit;
            }
            if ($limit < self::MIN_CENTRAL_POST_LIMIT || $limit > self::MAX_CENTRAL_POST_LIMIT) {
                throw new RuntimeException('保存されたマスターログ保存件数が範囲外です。');
            }

            return $limit;
        } catch (RuntimeException $exception) {
            $this->logger->warning('マスターログ保存件数を読み込めないため、既定値を使用します。', ['exception' => $exception]);

            return $this->defaultCentralPostLimit;
        }
    }

    public function defaultCentralPostLimit(): int
    {
        return $this->defaultCentralPostLimit;
    }

    public function setCentralPostLimit(int $limit): void
    {
        if ($limit < self::MIN_CENTRAL_POST_LIMIT || $limit > self::MAX_CENTRAL_POST_LIMIT) {
            throw new InvalidArgumentException(sprintf(
                'マスターログ保存件数は%d件以上%d件以下で入力してください。',
                self::MIN_CENTRAL_POST_LIMIT,
                self::MAX_CENTRAL_POST_LIMIT,
            ));
        }
        $this->repository->setCentralPostLimit($limit);
    }

    public function resetCentralPostLimit(): void
    {
        $this->repository->resetCentralPostLimit();
    }

    public function title(): string
    {
        try {
            return $this->repository->title() ?? $this->defaultTitle;
        } catch (RuntimeException $exception) {
            $this->logger->warning('サイト設定を読み込めないため、既定値を使用します。', [
                'exception' => $exception,
            ]);

            return $this->defaultTitle;
        }
    }

    public function defaultTitle(): string
    {
        return $this->defaultTitle;
    }

    public function setTitle(string $title): void
    {
        $title = trim($title);
        if ($title === '') {
            throw new InvalidArgumentException('サイトタイトルを入力してください。');
        }
        if (!mb_check_encoding($title, 'UTF-8')) {
            throw new InvalidArgumentException('サイトタイトルをUTF-8で入力してください。');
        }
        if (mb_strlen($title) > self::MAX_TITLE_CHARS) {
            throw new InvalidArgumentException(sprintf(
                'サイトタイトルは%d文字以内で入力してください。',
                self::MAX_TITLE_CHARS,
            ));
        }

        $this->repository->setTitle($title);
    }

    public function resetTitle(): void
    {
        $this->repository->resetTitle();
    }

    /** @param callable(): ?int $reader */
    private function readMessageLimit(callable $reader, int $default, string $label): int
    {
        try {
            $value = $reader();
            if ($value === null) {
                return $default;
            }
            $this->validateMessageLimit($value, $label);

            return $value;
        } catch (RuntimeException | InvalidArgumentException $exception) {
            $this->logger->warning(sprintf('%sを読み込めないため、既定値を使用します。', $label), [
                'exception' => $exception,
            ]);

            return $default;
        }
    }

    /** @param callable(): ?string $reader @param callable(string): void $validator */
    private function readStringSetting(callable $reader, string $default, string $label, callable $validator): string
    {
        try {
            $value = $reader() ?? $default;
            $validator($value);

            return $value;
        } catch (RuntimeException | InvalidArgumentException $exception) {
            $this->logger->warning(sprintf('%sを読み込めないため、既定値を使用します。', $label), [
                'exception' => $exception,
            ]);

            return $default;
        }
    }

    private function validateAdminName(string $name): void
    {
        if ($name === '') {
            throw new InvalidArgumentException('管理者名を入力してください。');
        }
        if (!mb_check_encoding($name, 'UTF-8') || mb_strlen($name) > self::MAX_ADMIN_NAME_CHARS) {
            throw new InvalidArgumentException(sprintf('管理者名は%d文字以内で入力してください。', self::MAX_ADMIN_NAME_CHARS));
        }
    }

    private function validateAdminEmail(string $email): void
    {
        if ($email === '') {
            return;
        }
        if (strlen($email) > self::MAX_ADMIN_EMAIL_CHARS || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('管理者メールアドレスを正しい形式で入力してください。');
        }
    }

    /** @param list<string> $words */
    private function validateProhibitedWords(array $words): void
    {
        if (count($words) > self::MAX_PROHIBITED_WORDS) {
            throw new InvalidArgumentException(sprintf(
                '投稿禁止ワードは%d件以内で入力してください。',
                self::MAX_PROHIBITED_WORDS,
            ));
        }
        foreach ($words as $word) {
            $invalidEncoding = !mb_check_encoding($word, 'UTF-8');
            $tooLong = mb_strlen($word) > self::MAX_PROHIBITED_WORD_CHARS;
            if ($word === '' || $invalidEncoding || $tooLong) {
                throw new InvalidArgumentException(sprintf(
                    '投稿禁止ワードは1件%d文字以内で入力してください。',
                    self::MAX_PROHIBITED_WORD_CHARS,
                ));
            }
        }
    }

    private function validateMessageLimit(int $value, string $label): void
    {
        if ($value < self::MIN_MESSAGE_LIMIT || $value > self::MAX_MESSAGE_LIMIT) {
            throw new InvalidArgumentException(sprintf(
                '%sは%d以上%d以下で入力してください。',
                $label,
                self::MIN_MESSAGE_LIMIT,
                self::MAX_MESSAGE_LIMIT,
            ));
        }
    }

    private function validateVisitorActiveSeconds(int $seconds): void
    {
        if ($seconds < self::MIN_VISITOR_ACTIVE_SECONDS || $seconds > self::MAX_VISITOR_ACTIVE_SECONDS) {
            throw new InvalidArgumentException(sprintf(
                '参加者カウント有効時間は%d秒以上%d秒以下で入力してください。',
                self::MIN_VISITOR_ACTIVE_SECONDS,
                self::MAX_VISITOR_ACTIVE_SECONDS,
            ));
        }
    }

    private function validateUndoWindowSeconds(int $seconds): void
    {
        if ($seconds < self::MIN_UNDO_WINDOW_SECONDS || $seconds > self::MAX_UNDO_WINDOW_SECONDS) {
            throw new InvalidArgumentException(sprintf(
                '投稿の削除可能時間は%d秒以上%d秒以下で入力してください。',
                self::MIN_UNDO_WINDOW_SECONDS,
                self::MAX_UNDO_WINDOW_SECONDS,
            ));
        }
    }

    private function validateArchiveRetentionDays(int $days): void
    {
        if ($days < self::MIN_ARCHIVE_RETENTION_DAYS || $days > self::MAX_ARCHIVE_RETENTION_DAYS) {
            throw new InvalidArgumentException(sprintf(
                '過去ログ保持日数は%d日以上%d日以下で入力してください。',
                self::MIN_ARCHIVE_RETENTION_DAYS,
                self::MAX_ARCHIVE_RETENTION_DAYS,
            ));
        }
    }

    private function validateArchivePublicDays(int $days): void
    {
        if ($days < self::MIN_ARCHIVE_PUBLIC_DAYS || $days > self::MAX_ARCHIVE_PUBLIC_DAYS) {
            throw new InvalidArgumentException(sprintf(
                '過去ログ公開日数は%d日以上%d日以下で入力してください。',
                self::MIN_ARCHIVE_PUBLIC_DAYS,
                self::MAX_ARCHIVE_PUBLIC_DAYS,
            ));
        }
    }

    private function validateServiceStartedAt(string $date): void
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();
        $hasErrors = $errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);
        if ($parsed === false || $hasErrors || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('サービス開始日を正しい日付で入力してください。');
        }
    }
}
