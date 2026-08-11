# sf-legacy

Symfony標準の最小構成で、設定からアプリタイトルを読み込むHello Worldアプリです。

## Docker Composeで起動

```bash
docker compose up --build
```

ブラウザで <http://localhost:8000> を開いてください。

Twig、CSS、JavaScriptの変更はViteによってブラウザへ自動反映されます。Viteの開発サーバーは <http://localhost:5173> を使用します。

本番用アセットをDockerで生成する場合は次を実行します。

```bash
docker compose run --rm vite npm run build
```

テストと静的解析もDocker Compose内で実行できます。

```bash
docker compose run --rm -e APP_ENV=test -e APP_DEBUG=1 app vendor/bin/pest
docker compose run --rm app vendor/bin/phpstan analyse --no-progress
docker compose run --rm app vendor/bin/phpcs
```

PSR-12違反を自動修正する場合は、次を実行します。

```bash
docker compose run --rm app vendor/bin/phpcbf
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
