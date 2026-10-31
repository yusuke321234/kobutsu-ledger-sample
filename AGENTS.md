# AGENTS.md — コブチョウ ミニ版

このリポジトリで作業する AI エージェント（と人）が最初に読むファイル。ここには要点だけを書き、詳しい規約は [`docs/conventions/`](docs/conventions/) に置く。

## このリポジトリ

副業・個人の古物商向けの台帳アプリ「コブチョウ」を、設計と開発規約を見せるために機能を絞って公開した版。**仕入・売却を記録すると、古物営業法の古物台帳（別記様式第15号）が記録から組み立てられ、PDF・CSV で出力できる。**
できること・含めないもの・ドメインの要は [01-project-overview.md](docs/conventions/01-project-overview.md)。

- **PHP 8.3 以上 / Laravel 13** + **Inertia 3 + React 19 + TypeScript（strict）+ Tailwind CSS 4**（Laravel 公式 React スターターキットの構成。Wayfinder・shadcn/ui・Vite+。Node 22.12 以上）
- 認証は Laravel 標準のセッション。PDF は mpdf。DB は既定で SQLite、テストはメモリ上の SQLite
- テストは PHPUnit の Feature テスト（Controller と 1:1）+ Playwright の E2E

```
app/
├── Http/Controllers/{Auth,Purchase,Sale,Ledger}/  # 画面と操作は同じコントローラ
├── Http/Requests/{同上}/                          # FormRequest（検証失敗は元の画面へ戻してエラーを載せる）
├── Http/Middleware/                               # HandleInertiaRequests / InitialLog / LogValidationError
├── Services/ + Contracts/Services/                # ビジネスロジック（Interface に DI バインド）
├── Repositories/ + Contracts/Repositories/        # DB アクセス（1テーブル = 1Repository）
├── Models/                                        # User / Purchase / PurchasePhoto / Sale
├── Enums/                                         # Kubun / VerificationMethod / SalesChannel
└── Consts/                                        # 定数だけのクラス
resources/js/                                      # React（pages/ layouts/ components/<ドメイン>/ hooks/ lib/ consts/ types/）
routes/web.php                                     # 画面・操作の全ルート（routes/api.php は無い）
tests/{Feature,e2e,PHPStan/Rules}/                 # Feature / 実ブラウザ / 規約を確かめる PHPStan の自作ルール
phpcs/Kobucho/                                     # PHP_CodeSniffer の自作ルール
```

DI は `app/Providers/AppServiceProvider.php` で Interface と実装をバインドする。

## 規約（`docs/conventions/`）

| ファイル | 内容 |
|---|---|
| [01-project-overview.md](docs/conventions/01-project-overview.md) | プロジェクト概要・範囲・ドメインの要 |
| [02-naming-conventions.md](docs/conventions/02-naming-conventions.md) | クラス・メソッド・変数・配列キーの命名（`xxxData` / `xxxList` / `xxxCount`）・ID と区分コードの型 |
| [03-coding-style.md](docs/conventions/03-coding-style.md) | **PHP スタイル（★最重要）**：変数の寿命・分岐とログ・if/return・FormRequest・メソッド定義 |
| [04-directory-structure.md](docs/conventions/04-directory-structure.md) | ディレクトリ構成 |
| [05-database-conventions.md](docs/conventions/05-database-conventions.md) | テーブル/カラム名・論理削除・クエリ（JOIN は控えめ） |
| [06-routing-and-language.md](docs/conventions/06-routing-and-language.md) | ルーティング・ルート名・言語方針 |
| [07-architecture.md](docs/conventions/07-architecture.md) | レイヤー構成・Repository の名前・Controller の4セクション・Consts と Enum |
| [08-testing-conventions.md](docs/conventions/08-testing-conventions.md) | Feature テスト 1:1・命名・data_provider・Factory・E2E |
| [09-frontend-style.md](docs/conventions/09-frontend-style.md) | React + Inertia + TS / Wayfinder / shadcn/ui / **pages は薄く** |
| [10-api-conventions.md](docs/conventions/10-api-conventions.md) | 画面とサーバーの境界（Inertia）・エラーの見せ方・ログ |
| [90-review-checklist.md](docs/conventions/90-review-checklist.md) | どの規約を何で機械チェックしているか・レビュー観点 |

読む順：本ファイル → 01 → 03（PHP）または 09（画面）→ 作業に関わるもの。規約に書いていないことは、既存のコードの書き方に合わせる。

## 画面とサーバーの境界（要点・詳細は 10）

- **画面の表示**：Controller が `Inertia::render('ledger', [...])`。**props の名前（1段目のキー）は camelCase**（`ledgerList` / `totalCount`）、中身は Service の snake_case 配列を**そのまま**渡す。`fn() =>` で包み、部分リロードで要らなければ計算しない
- **操作**：フォームで送る項目名は **snake_case**（変換しない）。処理後は **`back()`**、完了の案内は `Inertia::flash(FlashConsts::TOAST_DATA, Toast::success('...'))`
- **検証エラー**：元の画面へ戻してセッションに載せる → そのまま `form.errors.item_name`
- **見つからない・権限なし**：`abort(404/403, '文言')` → 画面からの操作ならトースト、画面遷移ならエラー画面（`AppServiceProvider::configureInertiaExceptions`）
- **型**：ID・区分コードは string／金額・数量は int／一覧は `xxxList` + `totalCount`（「もっと見る」は `Inertia::merge`）
- **URL**：画面側は Wayfinder（`@/routes` / `@/actions`）、PHP 側は `route('名前')` で組み立てる。文字列の URL を書かない

## 守ること

- 1機能 = **Controller / Service(+Interface) / Repository(+Interface) / FormRequest / Model / Migration / Factory / Feature テスト** を1セットで作る
- ビジネスロジックは Service、DB アクセスは Repository 経由、固定の種別は Enum、定数は `App\Consts\XxxConsts`
- フロントは **pages を薄く**（`<Head>`・`<h1>`・component の合成のみ）。state / 送信 / ハンドラは `components/<ドメイン>/` へ
- **PHP に Pint を使わない**（手書きの規約が正：public 関数のあいだは**3行**・`@param` / `@return` 付きの docblock・ログは**二重引用符**で `" . print_r($x, true)` の前後に空白・キャストは**空白なし** `(string)$x`・アロー関数は `fn()`）。**フロントは Vite+（Oxfmt + Oxlint）が正**
- ドメインの決まりを崩さない：**1仕入＝1売却**／台帳は保存せず記録から組み立てる／仕入・売却は**論理削除のみ**／一般経費は扱わない／本人確認はガイドの表示まで
- 個人情報（パスワード・相手方の住所・職業・年齢）をログに出さない
- **既存テストを壊さない**。決まらない判断は人に確認する

## 作業が終わったら（必ず実行）

```bash
php artisan test                                          # PHPUnit（Feature）
npm run lint:naming && composer phpstan && composer phpcs # 規約のチェック（命名・PHP の型と自作ルール・PHP の見た目）
npm run check && npm run types:check && npm run build     # 整形・Lint・型・本番ビルド（build は Wayfinder の生成も行う）
npm run e2e                                               # 画面の E2E（build のあと。専用の DB で php -S を起動する）
```

- 自動修正：`npm run check:fix`（フロント）/ `vendor/bin/phpcbf`（PHP の見た目）
- PHPStan のベースライン（`phpstan-baseline.neon`）は**0件**。新しい指摘はベースラインに入れずにコードを直す（ベースラインは、ライブラリの更新などで一度に多くの指摘が出たときに一時的に使うだけ。作り直しは `composer phpstan:baseline`）
- CI（GitHub Actions）が push とプルリクエストのたびに、test ジョブ（整形・Lint・型・規約のチェック・PHPUnit）と e2e ジョブ（Playwright）を流す。E2E は CI でだけ、失敗したテストを1回やり直す
- **push 前のフック**（`.githooks/pre-push`）：test ジョブと同じ解析とテスト（E2E を除く）を流し、落ちたら push を止める。有効にするには clone ごとに1回 `git config core.hooksPath .githooks`。今回だけ飛ばすなら `git push --no-verify`

## 手元で動かす

- `npm run dev:all`：Laravel（`artisan serve`）と Vite を同時に起動する（使う PHP は `PHP_BIN` で指定できる）
- サンプルデータ：`php artisan migrate:fresh --seed`（`demo@example.com` / パスワード `password`）
