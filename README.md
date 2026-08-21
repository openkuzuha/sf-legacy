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
設定管理画面には、Symfonyが現在使用している `APP_ENV` と変更手順も表示されます。
同じ実行環境欄で `CLOUD_MODE` の有効状態と、サイト設定・マスターログ・過去ログ・
各カウンターが現在使用している保存方式を読み取り専用で確認できます。
設定管理画面からパスワードを変更できます。変更後のハッシュは、ローカル環境では
`var/data/site-settings.json`、`CLOUD_MODE=1` では Valkey に保存され、
`ADMIN_PASSWORD_HASH` より優先されます。変更すると既存の管理セッションはすべて無効になります。

ログイン後の管理画面ではサイトタイトルを変更できます。管理画面で保存した値は
`APP_TITLE` より優先され、再起動せずに反映されます。「初期値に戻す」を実行すると
上書き値を削除します。保存先は通常実行時が `var/data/site-settings.json`、
`CLOUD_MODE=1` の場合はValkeyです。

マスターログの保存件数も管理画面から変更できます。既定値は500件です。
保存時点でマスターログを新しい投稿から指定件数へ切り詰め、その後の投稿・取込でも上限を維持します。
マスターログから除外された投稿は、ローカル環境の日別アーカイブまたはクラウド環境のS3アーカイブには残ります。
設定値はサイトタイトルや管理用パスワードと同じ保存先へ保存されます。

トップ画面の初期表示件数（旧版の `MSGDISP` 相当）も管理画面から変更できます。既定値は40件です。
閲覧者が個人用環境設定で表示件数を選択済みの場合は、その値が優先されます。

本文の最大行数（旧版の `MAXMSGLINE` 相当、既定値50行）と、1行の最大文字数
（`MAXMSGCOL` 相当、既定値200文字）も管理画面から変更できます。投稿フォームの
残量表示とサーバー側の投稿検証へ即時反映されます。
本文全体の最大文字数（`MAXMSGSIZE` 相当）は、旧版と同じ8400を初期値としつつ、
バイト数ではなく改行を含むUnicode文字数として扱います。

「現在の参加者」として数える最終アクセスからの有効時間（旧版の `CNTLIMIT` 相当）も
管理画面から変更できます。既定値は300秒です。

アクセスカウンターに表示するサービス開始日（旧版の `COUNTDATE` 相当）も管理画面から
変更できます。既定値は2026年8月12日で、日付の表示へ即時反映されます。

管理者投稿に使用する管理者名とメールアドレス（旧版の `ADMINNAME`、`ADMINMAIL` 相当）も
管理画面から変更できます。既定の管理者名は「管理人」、メールアドレスは未設定です。
メールアドレスを設定すると、トップ画面に「連絡先」のメールリンクを表示します。
一般の投稿者名に管理者名が含まれる場合は、旧版と同様に
`管理者名（騙り）` へ変換して表示します。将来は、WIPである投稿記事管理から
管理者投稿する際の既定名としても利用する予定です。

投稿禁止ワード（旧版の `NGWORD` 相当）も管理画面から変更できます。1行に1語ずつ、
最大100件まで設定でき、投稿者名・メールアドレス・題名・本文のいずれかに部分一致すると
投稿を拒否します。空行は無視され、同じ語を複数入力した場合は1件にまとめられます。

投稿禁止IPアドレス・CIDR（旧版の `HOSTNAME_POSTDENIED` 相当）も管理画面から変更できます。
1行に1件ずつ、IPv4・IPv6の単一アドレスまたはCIDRを最大100件まで設定できます。
保存時に形式とプレフィックス長を検証し、空行を無視して重複を除去します。投稿時の照合には
Symfonyの `IpUtils` を使用し、該当する利用者は掲示板を閲覧できますが投稿は403で拒否されます。
リバースプロキシやCDNの背後で運用する場合は、Symfonyのtrusted proxiesを正しく設定してください。

アクセス禁止IPアドレス・CIDR（旧版の `HOSTNAME_BANNED` 相当）も同じ形式で設定できます。
一致した利用者は投稿を含む公開側の掲示板全体を利用できなくなります。設定ミスから復旧できるよう
管理画面は遮断対象外です。この設定はアプリケーションへ到達したリクエストを403にする機能であり、
大量アクセスへの対策にはCDN、WAF、Webサーバーまたはファイアウォールを使用してください。

### Request IDと投稿者監査情報

すべてのHTTPレスポンスへ `X-Request-ID` を付与し、同じ値をSymfonyのログにも記録します。
信頼したリバースプロキシが渡したRequest IDだけを採用し、それ以外はアプリケーションが
暗号学的乱数から生成します。Productionでは次の環境変数を設定します。

```dotenv
REQUEST_ID_HEADER=X-Request-ID
REQUEST_ID_TRUSTED_PROXIES=127.0.0.1,::1
SYMFONY_TRUSTED_PROXIES=127.0.0.1,::1
AUDIT_HMAC_KEY=base64形式の監査用秘密鍵
```

監査用秘密鍵は次のコマンドで生成できます。

```bash
php bin/console bbs:audit-key:generate
```

Nginxからリバースプロキシする場合は、入口でクライアント指定値を上書きします。

```nginx
proxy_set_header X-Request-ID $request_id;
log_format main '$remote_addr request_id=$request_id "$request" $status';
```

PHP-FPMへFastCGIで接続する場合は `fastcgi_param HTTP_X_REQUEST_ID $request_id;` を使用します。
SymfonyのクライアントIP判定には、これとは別にtrusted proxiesを正しく設定してください。

投稿本文のJSONL・Valkey・S3アーカイブにはIPアドレスとUser-Agentを保存しません。
管理画面の「投稿者監査情報」は、記録しない・月次仮名化・生データの3モードです。
既定値は月次仮名化・30日で、ローカルでは権限0600の日別監査JSONL、クラウドモードでは
TTL付きのValkeyキーへ保存します。監査情報は公開用S3アーカイブへ複製しません。
NginxやCDNのアクセスログは管理画面の保存期限とは別なので、14日程度など必要最小限の
保持期間をインフラ側で設定してください。

旧形式の `host` と `user_agent` を既存データから消去する場合は、先にバックアップと
その削除期限を確認し、dry-runの結果を確認してから実行します。バックアップにも生データが残ります。

```bash
php bin/console bbs:audit:scrub-legacy
php bin/console bbs:audit:scrub-legacy --apply --backup-confirmed
```

運用状態では、投稿だけを停止する「投稿受付」と、公開画面全体を停止する
「メンテナンスモード」を個別に設定できます。メンテナンス中は公開側を503で応答し、
管理画面は復旧のため利用できます。任意の終了予定日時を設定すると案内画面に表示し、
HTTPの `Retry-After` ヘッダーにも反映します。

投稿者による直前の投稿削除（旧版の `ALLOW_UNDO` 相当）は、管理画面から有効・無効と
削除可能時間を変更できます。既定値は有効・86400秒（24時間）です。無効化すると削除ボタンを
表示せず、すでに発行済みの削除トークンによる操作も拒否します。

過去ログ保持日数（旧版の `OLDLOGSAVEDAY` 相当）も管理画面から変更できます。初期値の0は
無期限で、1〜3650日を指定すると今日を含む指定日数分だけを保持します。保存時と新規投稿時に、
期限を過ぎたローカルの日別ログまたはS3上の投稿オブジェクトを削除します。
保存期間とは別に、過去ログ画面へ公開する直近の日数も設定できます。初期値は30日です。
公開範囲は一覧・検索・トピック一覧・ダウンロードへ共通して適用され、範囲外の日付を
クエリーパラメーターへ直接指定した場合も拒否します。

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
