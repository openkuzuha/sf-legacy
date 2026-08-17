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
}
