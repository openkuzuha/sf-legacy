# sf-legacy

Symfony標準の最小構成で、設定からアプリタイトルを読み込むHello Worldアプリです。

## Docker Composeで起動

```bash
docker compose up --build
```

ブラウザで <http://localhost:8000> を開いてください。

開発用Valkeyも同時に起動します。アプリコンテナからは
`redis://valkey:6379`（環境変数 `VALKEY_URL`）、ホストからは
`redis://localhost:6379` で接続できます。データはDockerの
`valkey_data` ボリュームへAOF形式で永続化され、メモリ上限到達時には
データを削除せず書き込みエラーにする `noeviction` を使用します。
保存先はDocker内でも `.env` の `POST_STORAGE` を使用します。既定の
`POST_STORAGE=jsonl` では `/app/var/data/posts.jsonl`（ホスト側の
`var/data/posts.jsonl`）へ保存されます。Valkeyを使用する場合だけ
`POST_STORAGE=valkey` に変更してください。

保存処理は共通の `PostRepository` を介しており、通常のローカル実行では
`POST_STORAGE=jsonl` による単純なUTF-8 JSON Linesファイルも使用できます。
Valkeyにも同じJSONテキストを格納するため、データの取り出しや移行時に
Valkey固有形式を解釈する必要はありません。
投稿日時はUTCのRFC 3339形式（例: `2026-08-11T23:10:00Z`）で保存し、
取込時の解釈と画面表示には `APP_TIMEZONE`（既定値: `Asia/Tokyo`）を使用します。
投稿ごとに自動リンクの有効・無効を `auto_link` で保持し、取込時は省略すると有効になります。

Valkeyだけを起動して疎通を確認する場合は次を実行します。

```bash
docker compose up -d valkey
docker compose exec valkey valkey-cli ping
```

旧掲示板の公開HTMLまたは交換用JSON Linesは、共通Repositoryへ直接取り込めます。
元の投稿ID、日時、スレッド、返信関係を維持し、同じ投稿の再実行はスキップします。

```bash
docker compose run --rm app php bin/console bbs:import archive.html --format=legacy-html
docker compose run --rm app php bin/console bbs:import posts.jsonl --format=jsonl
cat posts.jsonl | docker compose run --rm -T app php bin/console bbs:import - --format=jsonl
```

`--dry-run`を付けると解析だけを行い、保存しません。入力URLも指定できます。

Twig、CSS、JavaScriptの変更はViteによってブラウザへ自動反映されます。Viteの開発サーバーは <http://localhost:5173> を使用します。

本番用アセットをDockerで生成する場合は次を実行します。

```bash
docker compose run --rm vite npm run build
```

テスト、静的解析、コーディング規約のチェックをDocker Compose内で一括実行できます。

```bash
docker compose run --rm -e APP_ENV=test -e APP_DEBUG=1 app composer check
```

PHPとTwigのコーディング規約違反を自動修正する場合は、次を実行します。

```bash
docker compose run --rm app vendor/bin/phpcbf
docker compose run --rm app vendor/bin/twig-cs-fixer fix templates
```

## Productionへデプロイ

ProductionサーバーではDockerとVite開発サーバーを使用しません。PHP 8.4、Composer、Node.jsを用意し、Webサーバーのドキュメントルートを `public/` に設定してください。

サーバー固有の設定を、Git管理しない `.env.local` に記述します。

```dotenv
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=十分に長いランダムな値
APP_TITLE="Open Kuzuha"
```

デプロイするリビジョンを配置した後、プロジェクトルートで次を実行します。

```bash
composer install --no-dev --classmap-authoritative --no-interaction
npm ci
npm run build
php bin/console cache:clear
```

実行ユーザーが `var/` に書き込めるよう権限を設定してください。`public/build/` はViteが生成する本番用アセットなので、Webサーバーから配信できる状態にします。

CIで `npm ci` と `npm run build` を実行し、生成された `public/build/` を成果物へ含める場合、ProductionサーバーにNode.jsは不要です。

## ローカルで起動

```bash
composer install
npm install
npm run dev
php -S localhost:8000 -t public
```

ブラウザで <http://localhost:8000> を開いてください。
