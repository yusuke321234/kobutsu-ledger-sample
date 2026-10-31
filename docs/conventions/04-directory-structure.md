# ファイル・ディレクトリ構成

Laravel プロジェクト内に React の画面（Inertia）を同居させるモノリシック構成。リポジトリの直下が Laravel のルート。

## バックエンド（Laravel）

```
app/
├── Consts/             # 定数クラス（マジックナンバー/文字列の集約。定数のみ）
├── Contracts/          # インターフェース定義
│   ├── Repositories/
│   └── Services/
├── Enums/              # PHP Enum（3個。固定のステータス・種別：Kubun / VerificationMethod / SalesChannel）
├── Http/
│   ├── Controllers/{Domain}/  # ドメイン別に分類（Auth / Purchase / Sale / Ledger）。画面と操作は同じコントローラ
│   ├── Middleware/            # Inertia の共有 props（HandleInertiaRequests）・開始ログ（InitialLog）・検証失敗のログ（LogValidationError）
│   └── Requests/{Domain}/     # Form Request（ドメイン別に分類）
├── Logging/            # リクエストごとにログの書き先を分けるドライバ（RequestPathLogHandler。10-api-conventions）
├── Models/             # User / Purchase / PurchasePhoto / Sale
├── Providers/          # AppServiceProvider（DI のバインド・アプリの既定値・レート制限・画面へのエラーの見せ方）
├── Repositories/       # DB アクセス（1テーブル = 1Repository）
├── Services/           # ビジネスロジック
└── Support/            # Toast（トーストのフラッシュデータを組み立てる）

routes/web.php          # 画面・操作の全ルート（Inertia。機能ごとに画面と操作を並べる。06-routing-and-language）
resources/views/
├── app.blade.php       # Inertia のルートテンプレート（既定のタイトルと説明）
└── pdf/ledger.blade.php  # 様式15号の帳票（mpdf で PDF にする）

database/
├── migrations/
├── factories/          # 各モデルの Factory（テスト用）
└── seeders/DatabaseSeeder.php   # 手元で画面を触るためのサンプルデータ（テストでは使わない）

tests/
├── Feature/            # Feature テスト。Controller・Service と 1:1（08-testing-conventions）
│                       #   AuthTest / PurchaseTest / PurchasePhotoTest / SaleTest / LedgerTest
│                       #   画面の土台（AppPageTest）・ログの振り分け（RequestLogTest）・アプリの既定値（AppServiceProviderTest）
├── PHPStan/Rules/      # 規約を確かめる PHPStan の自作ルール
├── e2e/                # 画面の E2E（Playwright）
└── TestCase.php        # Inertia の props・トーストを確かめる補助（assertInertiaProp / assertInertiaCount / assertInertiaToast）

phpcs/Kobucho/          # PHP_CodeSniffer の自作ルール（phpcs.xml から読む）
```

ドメイン分類: `Auth`, `Purchase`（写真の `PurchasePhoto` を含む）, `Sale`, `Ledger`

## フロントエンド（React + Inertia + TypeScript）

```
resources/js/
├── app.tsx                 # エントリポイント（createInertiaApp。レイアウトの割り当て・トースト置き場）
├── pages/                  # Inertia のページ（Controller の Inertia::render('xxx') と 1:1。薄く保つ）
│   ├── auth/               # login / register
│   ├── inventory.tsx  ledger.tsx  settings.tsx
│   └── error.tsx           # 403 / 404 / 500 / 503
├── layouts/                # app-layout.tsx（認証後。共通モーダルを持つ永続レイアウト）/ guest-layout.tsx
├── components/
│   ├── ui/                 # shadcn/ui（Radix。手を入れない）
│   ├── common/             # ヘッダー・モーダル基盤・トースト・入力エラーの表示等
│   └── auth/ purchase/ sale/ ledger/ settings/   # 画面の中身（ドメイン別）
├── hooks/                  # use-ui（共通モーダルの状態）/ use-flash-toast / use-counterparty-list / use-current-url
├── lib/                    # utils（cn）/ format（金額・日付・入力値）/ toast / form-errors / inventory-optimistic
├── consts/                 # 区分コード・表示名・固定文言（バックエンドの Enum と一致）
├── types/                  # 画面が受け取るデータの型（ドメインごとのファイル。index.ts は再エクスポートだけ）/ global.d.ts（Inertia の共有 props・フラッシュ）
└── actions/ routes/ wayfinder/   # Wayfinder の生成物（Git 管理外）
```

- 「バックエンドのコントローラ = Inertia のページ（または送信先の Wayfinder 関数）」が 1:1 で追えるようにする。
