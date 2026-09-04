# sf-legacy

## What is it?

sf-legacyは、ライセンス条件が明確でないまま流通しているKuzuhaScript系の
`bbs.php`をそのままGitHubで公開・再配布するのではなく、掲示板の挙動を参照して
MIT Licenseのもとで再構成したクローン実装です。本リポジトリに含まれるコードは、
可能な限り原本の表示と操作感に近い形で再実装しています。

フレームワークにはSymfonyを採用しています。Symfonyアプリケーションは
[Laravel Cloudでも動作する](https://laravel.com/cloud)一方、この規模の掲示板には
Laravelは機能が大きすぎると判断し、必要な構成を小さく保てるSymfonyを選びました。

## セットアップ

### 要件

- php 8.4以上
- composer

### VPSなどで動作させる場合

Apache/Nginx 環境ではVirtualHostが無いとしんどいです。

```
composer install
```

apache設定例

```
<IfModule mod_ssl.c>
<VirtualHost *:443>
    ServerName openkuzuha.example.net
    DocumentRoot /var/www/sf-legacy/public

    <Directory /var/www/sf-legacy/public>
        Options -Indexes +FollowSymLinks
        AllowOverride None
        Require all granted

        # Symfony's front controller handles routes that are not real files.
        FallbackResource /index.php
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/openkuzuha.example.net-error.log
    CustomLog ${APACHE_LOG_DIR}/openkuzuha.example.net-access.log combined

SSLCertificateFile /etc/letsencrypt/live/openkuzuha.example.net/fullchain.pem
SSLCertificateKeyFile /etc/letsencrypt/live/openkuzuha.example.net/privkey.pem
Include /etc/letsencrypt/options-ssl-apache.conf
</VirtualHost>
</IfModule>
```

---

## 初回起動時のセットアップ

初回起動時はまず /admin/setup/mode にリダイレクトされ、動作モード（クラウド／ローカル）
を選びます。決定すると /admin/setup へ進み、サイトタイトル・管理者情報・管理パスワードを
設定してセットアップを完了します。

### クラウドモードとローカルモード

- **ローカルモード**：投稿・設定・ログをすべてサーバー上のファイルへ保存します。VPSや
  単一サーバーでの運用向けです。
- **クラウドモード**：Valkeyと、S3互換オブジェクトストレージを保存先に使います。複数
  インスタンスでの運用向けです。事前に `VALKEY_URL` と `ARCHIVE_S3_*` を実際に到達できる
  値へ設定しておく必要があります（/admin/setup/mode でクラウドを選ぶと、保存前にこれらへの
  接続を確認します）。データごとの詳しい保存先は後述の「データ管理について」を参照してください。

どちらを選んだかは `.env.local` の `CLOUD_MODE` として記録され、**通常の管理画面からは
変更できません**（保存先が変わり、既存データが見えなくなるため）。選び直したい場合は、
サーバー上で次を実行してから /admin/setup/mode をやり直してください。

```bash
php bin/console bbs:setup:reset --with-mode
```

Docker Composeの `app` サービスのように、`CLOUD_MODE` が実行環境の環境変数として
すでに指定されている場合は、この選択画面は表示されず、指定されたモードでそのまま
動作します。

### 初回セットアップ

- サイトタイトル
- 管理者名
- 管理者メールアドレス
- サービス開始日（省略時は本日の日付）
- 管理パスワード

を決定します。これらをセットすると掲示板が動作可能になります。やり直したい場合は、
後述の `bbs:setup:reset` でリセットできます。


---

## データ管理について

保存先とその形式は `CLOUD_MODE` によって変わります。それぞれの実データがどこに保存されるかを
まとめます。いずれも `/admin/settings` の「実行環境」欄で、現在どちらが使われているか確認できます。

### 投稿・設定・ログ

| データ | ローカル | クラウド |
|---|---|---|
| 投稿（マスターログ） | JSON Linesファイル `var/data/posts.jsonl` | Valkey |
| サイト設定・管理パスワード | JSONファイル `var/data/site-settings.json` | Valkey |
| 過去ログ（アーカイブ） | 日別JSON Linesファイル `var/data/archive/` | S3互換オブジェクトストレージ |
| 投稿者監査ログ | 日別JSON Linesファイル `var/data/audit/` | Valkey（TTL付き） |

### カウンターデータ

| データ | ローカル | クラウド |
|---|---|---|
| アクセスカウンター（累計ページビュー） | テキストファイル `var/data/page-views.txt` | Valkeyキー `bbs:main:page-views`（INCRでアトミックに更新） |
| 参加者カウンター（現在の閲覧者数） | ローカルキャッシュファイル `var/cache/<env>/visitors.json` | 同左（`CLOUD_MODE`に関わらず変わりません） |

参加者カウンターだけは `CLOUD_MODE` に関わらず常にインスタンスローカルのキャッシュファイルを
使います。「直近数分の閲覧者数」がおおよそ分かればよく、複数インスタンス間で正確に合算する
必要がないためです。クラウドモードで複数インスタンスを運用する場合、この値は各インスタンスが
自分に来たアクセスだけを集計した近似値になる点に注意してください。

### セッション・レート制限

| データ | ローカル | クラウド |
|---|---|---|
| 管理画面ログインセッション | PHPネイティブのファイルセッション | Valkey |
| レート制限（ログイン試行・投稿頻度など） | ローカルファイルキャッシュ | 同左（`CLOUD_MODE`では切り替わりません） |

投稿フォームのCSRFトークンも管理セッションと同じ仕組み（Symfonyのセッション）に乗っているため、
クラウドモードで複数インスタンスを運用する場合、これをValkeyへ寄せたことでフォーム表示と投稿が
別インスタンスに振られても正しく検証できます。一方でレート制限（`admin_login`・`admin_setup`・
`post` など）はまだインスタンスローカルのキャッシュのままなので、複数インスタンス構成では
「5回/分」のような制限が実質インスタンス数倍になる点に注意してください。

---

## 管理用コマンド

いずれも `bin/console <コマンド名>` の形式で実行します（Docker Composeの場合は
`docker compose exec app php bin/console <コマンド名>`）。

### セットアップ・認証

- **`bbs:setup:reset`**：管理用パスワードとサイト情報（タイトル・管理者名・管理者メールアドレス）を
  初期化し、`/admin/setup` を再表示できるようにします。投稿データは削除しません。
  - `--force` / `-f`：確認プロンプトを省略します。
  - `--with-mode`：`.env.local` の `CLOUD_MODE` も削除し、動作モードの選択からやり直せるようにします。
    保存先が変わるため、選び直すと今のモードで保存されているデータは見えなくなります。

  ```
  bin/console bbs:setup:reset
  ```

- **`bbs:admin:password-hash`**：対話的に管理パスワードを入力し、ハッシュ化した
  `ADMIN_PASSWORD_HASH` を出力します。表示された値を `.env.local` などへ手動で設定してください。

  ```
  bin/console bbs:admin:password-hash
  ```

- **`bbs:audit-key:generate`**：投稿者監査情報の仮名化に使う `AUDIT_HMAC_KEY` を生成して出力します。

  ```
  bin/console bbs:audit-key:generate
  ```

### 投稿データ

- **`bbs:import`**：旧掲示板の公開HTML、またはJSON Linesから投稿を取り込みます。元の投稿ID・日時・
  スレッド・返信関係を維持し、同じ投稿の再実行はスキップします。
  - `source`（必須引数）：入力ファイル、URL、または標準入力を示す `-`
  - `--format=legacy-html|jsonl`：入力形式（省略時は拡張子や内容から自動判定）
  - `--location=名前`：取り込み先の名前（既定値: `main`）
  - `--dry-run`：解析のみ行い保存しない

  ```
  bin/console bbs:import archive.html --format=legacy-html
  bin/console bbs:import posts.jsonl --format=jsonl
  cat posts.jsonl | bin/console bbs:import - --format=jsonl
  ```

- **`bbs:archive:rebuild`**：正本の投稿ログ（マスターログ）から日別／S3アーカイブを再構築します。
  アーカイブを消してしまった場合や、保存形式を変更した後の再生成に使います。

  ```
  bin/console bbs:archive:rebuild
  ```

- **`bbs:data:reset`**：すべての投稿と過去ログを削除します。セットアップ状態（管理パスワードなど）は
  変更しません。
  - `--force` / `-f`：確認プロンプトを省略します。

  ```
  bin/console bbs:data:reset
  ```

### 投稿者監査ログ

- **`bbs:audit:scrub-legacy`**：既存の投稿データから旧形式で残っている生IPアドレスと
  User-Agentを消去します。既定ではdry-runで対象件数のみ表示し、実際に書き換えるには
  バックアップを確認したうえで両方のオプションを指定します。
  - `--apply`：実際にデータを書き換える
  - `--backup-confirmed`：バックアップを確認済みとして実行する

  ```
  bin/console bbs:audit:scrub-legacy
  bin/console bbs:audit:scrub-legacy --apply --backup-confirmed
  ```
