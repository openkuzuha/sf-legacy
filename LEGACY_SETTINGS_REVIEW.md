# 旧版設定項目の移行レビュー

調査対象: `../kuzuha-legacy/kuzuhaphp`

旧版には `conf.php` の72項目と、過去ログ・ツリー表示モジュール固有の7項目を合わせて、79項目の設定があります。
現行の管理画面では、サイトタイトルや管理パスワードに加え、投稿・表示・過去ログ・
アクセス制限や運用状態に関する設定を変更できます。

## 優先して対応したい項目

### 1. IPアドレスとUser-Agentの記録

旧版の初期値は次のとおりで、どちらも記録しません。

- `IPREC=0`
- `UAREC=0`

対応済みです。投稿本文の保存先からIPアドレスとUser-Agentを分離し、Request ID付きの
期限付き監査ログへ保存します。既定値は月次のHMAC仮名化・30日です。

管理画面には次の設定を追加しました。

- 記録しない
- 仮名化して記録
- 生IPアドレスとUser-Agentを記録する

旧版の逆引きホスト名や匿名プロクシ推測は復刻しません。既存の `host`・`user_agent` は
確認付き移行コマンドでローカルJSONL・Valkey・S3から消去できます。

### 2. 投稿本文の総バイト数制限

旧版の制限値は次のとおりです。

- `MAXMSGCOL=200`: 1行の最大桁数
- `MAXMSGLINE=50`: 最大行数
- `MAXMSGSIZE=8400`: 本文全体の最大文字数として対応（旧版はbyte数）

現行には1行200文字と50行の制限がありますが、総サイズ制限はありません。大量のマルチバイト文字なども考慮し、総バイト数の制限を追加するのが安全です。

### 3. 初期表示件数

旧版の `MSGDISP` は10件ですが、現行の初期値は40件です。意図した変更なら問題ありません。

管理画面に追加する場合は「初期表示件数」とし、閲覧者がCookieで変更した値を優先する形が自然です。

## 現行との対応表

| 旧設定 | 現行 | 提案 |
|---|---|---|
| `BBSTITLE` | `APP_TITLE`と管理画面の上書き値 | 実装済み |
| `ADMINPOST` | `ADMIN_PASSWORD_HASH`と管理画面の上書き値 | 実装済み |
| `TIMEZONE` | `APP_TIMEZONE` | 当面は現在値表示でよい |
| `LOGSAVE` | 管理画面からマスターログ保存件数を変更可能（既定値500） | 実装済み |
| `MSGDISP` | 初期値40、管理画面から変更可能 | 対応済み |
| `MAXMSGCOL` | 初期値200、管理画面から変更可能 | 対応済み |
| `MAXMSGLINE` | 初期値50、管理画面から変更可能 | 対応済み |
| `MAXMSGSIZE` | 初期値8400文字、管理画面から変更可能 | 文字数として対応済み |
| `CNTLIMIT` | 初期値300秒、管理画面から変更可能 | 参加者カウント有効時間として対応済み |
| `COUNTDATE` | 初期値 `2026/08/12`、管理画面から変更可能 | サービス開始日として対応済み |
| `ADMINNAME` | 初期値「管理人」、管理画面から変更可能 | 一般投稿の騙り表示に対応済み。管理者投稿の既定名にも使用予定 |
| `ADMINMAIL` | 初期値なし、管理画面から変更可能 | トップ画面の連絡先として対応済み |
| `NGWORD` | 管理画面から1行1語で変更可能 | 対応済み |
| `ALLOW_UNDO` | 初期値ON・期限24時間、管理画面から変更可能 | 対応済み |
| `OLDLOGSAVEDAY` | 初期値は無期限、管理画面から0〜3650日で変更可能 | 保持日数として対応済み |
| `HOSTNAME_POSTDENIED` | 管理画面からIPv4・IPv6の単一IP/CIDRを変更可能 | ホスト名の逆引き・正規表現を廃止して対応済み |
| `HOSTNAME_BANNED` | 管理画面からIPv4・IPv6の単一IP/CIDRを変更可能 | 管理画面を除く掲示板全体のアクセス拒否として対応済み |
| `MINPOSTSEC` | Symfony RateLimiter | 管理画面候補だが構成変更が必要 |
| `MAXPOSTSEC` | 1時間30件の制限 | 意味が異なるため別名推奨 |
| `INFOPAGE` | `#` 固定 | URL設定を追加推奨 |
| `HANDLENAMES` | 未実装 | 低優先度 |
| `AUTOLINK` | 初期ON、Cookieで個人設定 | 現状でよい |

## 表示色

旧版の次の8色は現行でも使われています。

- `C_BACKGROUND`
- `C_TEXT`
- `C_A_COLOR`
- `C_A_VISITED`
- `C_A_ACTIVE`
- `C_A_HOVER`
- `C_SUBJ`
- `C_QMSG`

現在はサーバー設定として固定され、利用者側の「個人用環境設定」でブラウザごとに上書きする設計です。管理画面へ追加する場合は「サイト初期配色」という独立したカードにするのがよさそうです。

## 移行不要と考えられる項目

次の設定は、現行ではルーティング、Webサーバー、Twig、S3/Valkey、CSSなどへ役割が移っているため、管理画面へ持ち込まなくてよさそうです。

- `CGIURL`
- `REFCHECKURL`
- `BBSHOST`
- `LOGFILENAME`
- `COUNTFILE`
- `CNTFILENAME`
- `TEMPLATE`
- `TEMPLATE_ADMIN`
- `TEMPLATE_LOG`
- `TEMPLATE_TREEVIEW`
- `OLDLOGFILEDIR`
- `ZIPDIR`
- `OLDLOGFMT`
- `GZIPU`
- `COUNTLEVEL`
- `FOLLOWWIN`
- `ADMINKEY`
- `SHOW_PRCTIME`
- `TXTFOLLOW`
- `TXTAUTHOR`
- `TXTTHREAD`
- `TXTTREE`
- `TXTUNDO`
- `C_BRANCH`
- `C_UPDATE`
- `C_NEWMSG`
- `C_QUERY`

## 旧版設定の全一覧

### URL・ファイル・テンプレート

- `CGIURL`
- `REFCHECKURL`
- `BBSHOST`
- `LOGFILENAME`
- `OLDLOGFILEDIR`
- `ZIPDIR`
- `TEMPLATE`
- `TEMPLATE_ADMIN`
- `TEMPLATE_LOG`
- `TEMPLATE_TREEVIEW`

### 掲示板・管理者

- `BBSTITLE`
- `INFOPAGE`
- `ADMINNAME`
- `ADMINMAIL`
- `ADMINPOST`
- `ADMINKEY`

### 投稿・動作

旧版の `RUNMODE`（通常・投稿停止・全体停止）は、「投稿受付」と「メンテナンスモード」に
分けて実装済みです。全体停止時は公開側を503にし、復旧用の管理画面は利用可能なままにします。

- `BBSMODE_ADMINONLY`
- `ALLOW_UNDO`
- `SHOW_READNEWBTN`
- `GZIPU`
- `LOGSAVE`
- `MSGDISP`
- `CHECKCOUNT`
- `MAXMSGCOL`
- `MAXMSGLINE`
- `MAXMSGSIZE`
- `MINPOSTSEC`
- `MAXPOSTSEC`
- `AUTOLINK`
- `FOLLOWWIN`
- `IPREC`
- `UAREC`
- `IPPRINT`
- `UAPRINT`
- `SPTIME`
- `COOKIE`
- `SHOW_SELFFOLLOW`

### カウンター・時刻

- `COUNTDATE`
- `COUNTFILE`
- `COUNTLEVEL`
- `CNTFILENAME`
- `CNTLIMIT`
- `TIMEZONE`

### 色・表示文字

- `C_BACKGROUND`
- `C_TEXT`
- `C_A_COLOR`
- `C_A_VISITED`
- `C_A_ACTIVE`
- `C_A_HOVER`
- `C_SUBJ`
- `C_QMSG`
- `C_ERROR`
- `TXTFOLLOW`
- `TXTAUTHOR`
- `TXTTHREAD`
- `TXTTREE`
- `TXTUNDO`
- `FSUBJ`
- `ANONY_NAME`

### 過去ログ

- `OLDLOGFMT`
- `OLDLOGBTN`
- `OLDLOGSAVESW`
- `OLDLOGSAVEDAY`
- `MAXOLDLOGSIZE`

### リンク・アクセス制限・詳細設定

- `BBSLINK`
- `HOSTNAME_POSTDENIED`
- `HOSTNAME_BANNED`
- `NGWORD`
- `HANDLENAMES`
- `SHOW_COUNTER`
- `DATEFORMAT`
- `SHOW_PRCTIME`

### 過去ログモジュール固有

- `MULTIPLESEARCH`
- `C_QUERY`
- `MAXKEYWORDS`

### ツリー表示モジュール固有

- `C_BRANCH`
- `C_UPDATE`
- `C_NEWMSG`
- `TREEDISP`

## 管理画面の構成案

### 基本設定

- サイトタイトル
- 広報室URL
- 連絡先

### 投稿設定

- マスターログ保存件数
- 初期表示件数
- 本文最大行数
- 1行の最大文字数
- 投稿の最大総バイト数
- UNDOの有効・無効と期限

### プライバシー

- IPアドレスの記録
- User-Agentの記録

### アクセス制限

- NGワード
- 投稿禁止IPアドレス・CIDR（実装済み）
- アクセス禁止IPアドレス・CIDR（実装済み）

旧版の `HOSTNAME_POSTDENIED` は接続元IPを逆引きしたホスト名へ正規表現を適用していました。
現行ではDNS逆引きの遅延や正規表現の設定リスクを避け、IPv4・IPv6の単一IPまたはCIDRを
1行に1件、最大100件まで設定する方式へ変更しています。Symfonyの `IpUtils` で接続元IPを
照合し、一致した場合も閲覧は許可して投稿だけを拒否します。CDN・リバースプロキシ配下では、
`Request::getClientIp()` が実際の接続元を返すようtrusted proxiesの設定が必要です。

`HOSTNAME_BANNED` も同じIP/CIDR形式へ変更し、該当する接続元から公開側の掲示板全体への
アクセスを拒否します。設定ミス時の復旧経路を確保するため、管理画面は遮断対象に含めません。
アプリケーションでの403応答なので、大量アクセス対策はCDN・WAF・Webサーバーなどで行います。

### デザイン

- サイト初期配色

### システム情報

- `APP_ENV`
- `APP_TIMEZONE`
- 保存バックエンドなどの読み取り専用情報

## 実装順の提案

最初は事故防止効果の高い次の3項目を優先します。

1. IPアドレスを記録するか
2. User-Agentを記録するか
3. 投稿本文の最大総バイト数
