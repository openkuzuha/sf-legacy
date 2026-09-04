<?php

namespace App\Settings;

use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * 初回セットアップにおける「ローカルモード／クラウドモード」の決定状態を扱う。
 *
 * CLOUD_MODEは`.env`に既定値（0）を持つため、`%app.cloud_mode%`の値だけでは
 * 「利用者がまだ選んでいない」のか「ローカルモードを選んだ結果0になっている」のかを
 * 区別できない。そこで以下のどちらかが成立する場合に「決定済み」とみなす。
 *
 * - 実行環境の環境変数でCLOUD_MODEが指定されている（.envやDotenvはデフォルトで
 *   putenv()しないため、getenv()はDockerやホスティング先が渡した実envのみを見る）
 * - このセットアップ画面が.env.localへCLOUD_MODEを書き込み済みである
 */
final class CloudModeSetup
{
    public function __construct(
        #[Autowire(param: 'app.cloud_mode')]
        private readonly bool $cloudMode,
        private readonly EnvLocalWriter $envLocalWriter,
    ) {
    }

    public function current(): bool
    {
        return $this->cloudMode;
    }

    public function isFixedByEnvironment(): bool
    {
        return getenv('CLOUD_MODE') !== false;
    }

    public function isDecided(): bool
    {
        return $this->isFixedByEnvironment() || $this->envLocalWriter->has('CLOUD_MODE');
    }

    public function decide(bool $cloudMode): void
    {
        if ($this->isFixedByEnvironment()) {
            throw new RuntimeException(
                'CLOUD_MODEは実行環境の環境変数で指定されているため、この画面からは変更できません。',
            );
        }
        if ($this->isDecided()) {
            throw new RuntimeException('動作モードはすでに決定されています。');
        }

        $this->envLocalWriter->upsert(['CLOUD_MODE' => $cloudMode ? '1' : '0']);
    }
}
