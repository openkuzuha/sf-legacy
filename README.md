# sf-legacy

## What is it?

sf-legacyは、ライセンス条件が明確でないまま流通しているKuzuhaScript系の
`bbs.php`をそのままGitHubで公開・再配布するのではなく、掲示板の挙動を参照して
MIT Licenseのもとで再構成したクローン実装です。本リポジトリに含まれるコードは、
可能な限り原本の表示と操作感に近い形で再実装しています。

フレームワークにはSymfonyを採用しています。Symfonyアプリケーションは
[Laravel Cloudでも動作する](https://laravel.com/cloud)一方、この規模の掲示板には
Laravelは機能が大きすぎると判断し、必要な構成を小さく保てるSymfonyを選びました。

---

## 仕様







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
Composeでは `CLOUD_MODE=1` により投稿とページビューをValkeyへ保存します。
通常のローカル実行では `.env` の `CLOUD_MODE=0` により、投稿を単純なUTF-8
JSON Linesファイル `var/data/posts.jsonl`、ページビューをファイルへ保存します。
保存処理は共通の `PostRepository` を介しています。
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

すべての投稿と過去ログを初期化する場合は次を実行します。通常は確認を求め、
自動実行時のみ `--force`（または `-f`）で確認を省略できます。

```bash
docker compose exec app php bin/console bbs:data:reset
```

管理画面は `/admin` です。管理用パスワードのハッシュは対話コマンドで生成し、
表示された `ADMIN_PASSWORD_HASH` を `.env.local` などへ設定します。パスワードは
画面にもコマンドライン引数にも表示されません。

```bash
docker compose exec app php bin/console bbs:admin:password-hash
```

ログイン後は「設定管理」と「投稿記事管理」の2画面を利用できます。投稿記事管理は現在WIPです。
設定管理画面からパスワードを変更できます。変更後のハッシュは、ローカル環境では
`var/data/site-settings.json`、`CLOUD_MODE=1` では Valkey に保存され、
`ADMIN_PASSWORD_HASH` より優先されます。変更すると既存の管理セッションはすべて無効になります。

ログイン後の管理画面ではサイトタイトルを変更できます。管理画面で保存した値は
`APP_TITLE` より優先され、再起動せずに反映されます。「APP_TITLEに戻す」を実行すると
上書き値を削除します。保存先は通常実行時が `var/data/site-settings.json`、
`CLOUD_MODE=1` の場合はValkeyです。

開発用のS3互換ストレージとしてMinIOも起動します。S3 APIは
`http://localhost:9000`、管理コンソールは `http://localhost:9001` です。
初回起動時に `bbs-archive` バケットが自動作成され、データはDockerの
`minio_data` ボリュームへ永続化されます。接続情報とバケット名は `.env` の
`MINIO_ROOT_USER`、`MINIO_ROOT_PASSWORD`、`MINIO_BUCKET` で変更できます。
アーカイブのオブジェクトキーは既定で `archives/` 以下に保存します。
`ARCHIVE_S3_PREFIX` で変更でき、空文字を指定するとバケット直下を使用します。
クラウドモードでは投稿ごとに
`archives/main/YYYY/MM/DD/0000000001.json` 形式で保存します。
Composeのアプリコンテナでは `CLOUD_MODE=1` が設定され、ホストから直接実行する
場合は `.env` の既定値 `CLOUD_MODE=0` が使用されます。

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

## リンク集

トップ画面の「広報室 | 過去ログ」に続くリンクは、`config/links.md` で管理します。
Markdownの箇条書きとして、1行に1件ずつ表示名とURLを記述してください。

```markdown
- [openkuzuha](https://github.com/openkuzuha)
- [sf-legacy](https://github.com/openkuzuha/sf-legacy)
```

リンクはファイル内の順番を維持し、画面上では次のように `|` 区切りで横に並びます。

```text
広報室 | 過去ログ | openkuzuha | sf-legacy
```

リンク以外のMarkdown要素は画面へ渡しません。HTMLは除去され、`javascript:` などの
危険なURLもリンクとして表示されません。ファイルが存在しない、または読み込めない場合は、
追加リンクを表示せずにトップ画面を表示します。

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
