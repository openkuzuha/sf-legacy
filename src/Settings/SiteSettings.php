<?php

namespace App\Settings;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class SiteSettings
{
    public const int MAX_TITLE_CHARS = 100;
    public const int MIN_CENTRAL_POST_LIMIT = 1;
    public const int MAX_CENTRAL_POST_LIMIT = 100000;

    public function __construct(
        private readonly SiteSettingsRepository $repository,
        private readonly LoggerInterface $logger,
        private readonly string $defaultTitle,
        private readonly int $defaultCentralPostLimit,
    ) {
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
}
