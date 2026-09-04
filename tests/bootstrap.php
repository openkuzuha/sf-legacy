<?php

require dirname(__DIR__) . '/vendor/autoload.php';

(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

// テストスイート全体を、実行コンテナがCLOUD_MODEを実環境変数として渡しているか
// どうかから独立させる。CloudModeSetup::isFixedByEnvironment()はgetenv()で
// 「.env/.env.localではなく本物の実行環境変数か」を見分けるため、compose.yamlの
// CLOUD_MODE設定を外した環境でテストを実行すると、動作モードを扱わない
// テスト（管理画面ログインなど）まで未決定扱いになってしまう。
// 動作モード自体を検証するテストは、各テストの中で一時的にunsetする。
if (getenv('CLOUD_MODE') === false) {
    putenv('CLOUD_MODE=1');
}
