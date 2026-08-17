<?php

namespace App\Settings;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class SiteSettings
{
    public const int MAX_TITLE_CHARS = 100;

    public function __construct(
        private readonly SiteSettingsRepository $repository,
        private readonly LoggerInterface $logger,
        private readonly string $defaultTitle,
    ) {
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
