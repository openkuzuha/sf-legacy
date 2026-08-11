# sf-legacy

Symfony標準の最小構成で、設定からアプリタイトルを読み込むHello Worldアプリです。

## Docker Composeで起動

```bash
docker compose up --build
```

ブラウザで <http://localhost:8000> を開いてください。

## ローカルで起動

```bash
composer install
php -S localhost:8000 -t public
```

ブラウザで <http://localhost:8000> を開いてください。
