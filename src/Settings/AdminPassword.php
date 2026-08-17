<?php

namespace App\Settings;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class AdminPassword
{
    public const int MIN_CHARS = 12;
    public const int MAX_CHARS = 4096;

    public function __construct(
        private readonly SiteSettingsRepository $repository,
        private readonly LoggerInterface $logger,
        private readonly string $defaultHash,
    ) {
    }

    public function verify(string $password): bool
    {
        $hash = $this->hash();

        return $hash !== '' && password_verify($password, $hash);
    }

    public function fingerprint(): string
    {
        return hash('sha256', $this->hash());
    }

    public function isConfigured(): bool
    {
        return $this->hash() !== '';
    }

    public function change(string $currentPassword, string $newPassword, string $confirmation): void
    {
        if (!$this->verify($currentPassword)) {
            throw new InvalidArgumentException('現在のパスワードが正しくありません。');
        }
        if ($newPassword !== $confirmation) {
            throw new InvalidArgumentException('新しいパスワードと確認入力が一致しません。');
        }
        if (mb_strlen($newPassword) < self::MIN_CHARS) {
            throw new InvalidArgumentException(sprintf('新しいパスワードは%d文字以上で入力してください。', self::MIN_CHARS));
        }
        if (mb_strlen($newPassword) > self::MAX_CHARS) {
            throw new InvalidArgumentException(sprintf('新しいパスワードは%d文字以内で入力してください。', self::MAX_CHARS));
        }
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->repository->setAdminPasswordHash($hash);
    }

    private function hash(): string
    {
        try {
            return $this->repository->adminPasswordHash() ?? $this->defaultHash;
        } catch (RuntimeException $exception) {
            $this->logger->warning('管理用パスワード設定を読み込めないため、環境変数を使用します。', [
                'exception' => $exception,
            ]);

            return $this->defaultHash;
        }
    }
}
