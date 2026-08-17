<?php

namespace App\Settings;

use Predis\Client;
use Predis\PredisException;
use RuntimeException;

final class ValkeySiteSettingsRepository implements SiteSettingsRepository
{
    private const string TITLE_KEY = 'bbs:settings:site-title';

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
}
