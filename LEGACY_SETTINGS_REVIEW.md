# 旧版設定項目の移行レビュー

調査対象: `../kuzuha-legacy/kuzuhaphp`

旧版には `conf.php` の72項目と、過去ログ・ツリー表示モジュール固有の7項目を合わせて、79項目の設定があります。
現行の管理画面から変更できるのは、サイトタイトルと管理パスワードの2項目です。

## 優先して対応したい項目

### 1. IPアドレスとUser-Agentの記録

旧版の初期値は次のとおりで、どちらも記録しません。

- `IPREC=0`
- `UAREC=0`

現行は `SubmitController` で投稿者のIPアドレスとUser-Agentを無条件に保存しています。旧版からの挙動変更であり、プライバシー上も最優先で設定可能にした方がよい項目です。

管理画面には次の設定を追加する案が考えられます。

- IPアドレスを記録する
- User-Agentを記録する
- 記録した情報は管理画面の記事管理でのみ表示する

旧版互換の初期値は、どちらもOFFです。

### 2. 投稿本文の総バイト数制限

旧版の制限値は次のとおりです。

- `MAXMSGCOL=200`: 1行の最大桁数
- `MAXMSGLINE=50`: 最大行数
- `MAXMSGSIZE=8400`: 1投稿の最大サイズ（byte）

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
| `MSGDISP` | 初期値40固定 | 管理画面候補 |
| `MAXMSGCOL` | 200固定 | 管理画面候補 |
| `MAXMSGLINE` | 50固定 | 管理画面候補 |
| `MAXMSGSIZE` | 未実装 | 追加推奨 |
| `MINPOSTSEC` | Symfony RateLimiter | 管理画面候補だが構成変更が必要 |
| `MAXPOSTSEC` | 1時間30件の制限 | 意味が異なるため別名推奨 |
| `AUTOLINK` | 初期ON、Cookieで個人設定 | 現状でよい |
| `ALLOW_UNDO` | 常時ON、期限24時間 | 有効・無効と期限を設定候補 |
| `CNTLIMIT` | 300秒固定 | 管理画面候補 |
| `COUNTDATE` | `2026/08/12` 固定 | 管理画面候補 |
| `INFOPAGE` | `#` 固定 | URL設定を追加推奨 |
| `ADMINNAME` | 未使用 | 投稿管理実装時の候補 |
| `ADMINMAIL` | 未使用 | 連絡先URLまたはメールとして候補 |
| `NGWORD` | 未実装 | 投稿管理と合わせて追加推奨 |
| `HOSTNAME_BANNED` | 未実装 | IP/CIDR形式へ改めて追加候補 |
| `HOSTNAME_POSTDENIED` | 未実装 | IP/CIDR形式へ改めて追加候補 |
| `HANDLENAMES` | 未実装 | 低優先度 |

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
- 投稿禁止IP/CIDR

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
