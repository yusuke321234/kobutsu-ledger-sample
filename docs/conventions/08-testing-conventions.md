# テスト (～Test.php) の規則

## 必須ルール

### ファイル単位 (Controller-Service-Test は 1:1:1)

・**Controller / Service / Test は基本的に 1 : 1 : 1 の関係。**
    - 例: `PurchaseController` ↔ `PurchaseService` ↔ `tests/Feature/PurchaseTest.php`（テストファイルは `AuthTest` / `PurchaseTest` / `PurchasePhotoTest` / `SaleTest` / `LedgerTest`）
    - 1 つの Controller に対するテストは 1 つの `XxxTest.php` に集約する。 機能ごとに `XxxStockTest.php` などサブ分割しない
    - 同じ Service を別の名前のテストファイルに分散して書かない (テスト対象が散らかってレビュー時に追えなくなる)
    - 同じファイル内で複数のテストメソッドを並べる (`getListTest`, `updateSuccessTest` など)
    - **例外**: 別 Service のテストは別ファイルに分ける。 「Service ごとに 1 ファイル」を守る
    - setUp が大きく違うテストを同じファイルに置く場合は、共通部分のみ setUp に置き、テスト固有のシードは各テストメソッドの冒頭で行う (各テストの独立性を保つ)

### メソッド名

- **日本語を使わない**（クラス内のすべてのメソッド名）
- テストメソッドは `xxxTest` の形式（末尾を `Test` にする）
- data_provider は `data_provider_for_xxxTest` の形式
- 命名の基準は「テスト対象のメソッド名」に合わせる

| 種別              | パターン                   | 例                                    |
| ----------------- | -------------------------- | ------------------------------------- |
| テストメソッド    | `xxxTest`                  | `getListTest`, `updateNameTest`       |
| data_provider     | `data_provider_for_xxxTest`| `data_provider_for_getListTest`       |
| private メソッド  | camelCase（英語）          | `getLedger`, `uploadWithUpdate`       |

**NG 例**
```php
public function 仕入一覧取得テスト() { ... }
public function 台帳PDF出力テスト() { ... }
public function data_provider_for_getListTest_NG系() { ... }
```

**OK 例**
```php
public function getListTest() { ... }
public function exportPdfReturnsInlinePdfTest() { ... }
public function data_provider_for_getListValidationTest() { ... }
```

### テスト属性（PHPUnit 11+ / Laravel 13）

- テストメソッド名は `xxxTest`（`test` 接頭辞でない）ため、**`#[Test]` 属性**を付けて PHPUnit にテストと認識させる。
- data_provider は **`#[DataProvider('data_provider_for_xxxTest')]` 属性**で紐付ける。
- **docコメントの `@test` / `@dataProvider` は使わない**（PHPUnit 11/12/13 で非推奨・除去方向）。
- import: `use PHPUnit\Framework\Attributes\Test;` / `use PHPUnit\Framework\Attributes\DataProvider;`

```php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[Test]
#[DataProvider('data_provider_for_getListTest')]
public function getListTest(string $testCase, array $queryData, array $expected): void
{
    // ...
}
```

### data_provider のテストケース

- data_provider メソッドは **`public static function`**（PHPUnit 10+ は static 必須）。
- **外側のデータセット名キー**（`'unauthenticated'` 等）は英語の snake_case ラベル。
- 各テストケースの配列は **キー名を付与**し、その**内側キーはテストメソッドの引数名（camelCase）と一致させる**（PHPUnit 10+ は文字列キーを名前付き引数としてバインドするため。例: 引数 `$testCase` ⇔ キー `'testCase'`。不一致だと `Unknown named parameter` エラー）。
- OK 系 / NG 系は同じ data_provider と同じテストメソッドにまとめる（分岐は `$testCase` で判定）。

**NG 例**（内側キーが引数名と不一致 / 位置指定）
```php
return [
    '取得成功（自分の仕入だけ）' => [
        'own_only_default',                 // ← キー名なし（位置指定）
        [],
    ],
    'unauthenticated' => [
        'test_case' => 'unauthenticated',   // ← snake_case で引数 $testCase と不一致 → エラー
        'query_data' => [],
    ],
];
```

**OK 例**（provider は static、内側キー = 引数名 camelCase）
```php
public static function data_provider_for_getListTest(): array
{
    return [
        'unauthenticated' => [
            'testCase' => 'unauthenticated',
            'queryData' => [],
            'expected' => ['result' => 'unauthenticated'],
        ],
        'own_only_default' => [
            'testCase' => 'own_only_default',
            'queryData' => [],
            'expected' => [
                'result' => 'ok',
                'total' => 2,
            ],
        ],
    ];
}
```

### テストメソッドの引数名

- data_provider の**内側キー名（camelCase）**と一致させる：`string $testCase`, `array $queryData`, `array $expected`（PHPUnit が名前付き引数でバインドするため、キーと引数名が一致している必要がある）

### シーディング・テストデータ

- テスト実行時は `RefreshDatabase` トレイトで DB をリセットする（テストはメモリ上の SQLite。`phpunit.xml`）。
- シーディングは原則 `setUp()` 内で完結させる（テストメソッド内で個別にシード関数を呼ぶ形は避ける）。
- **役割分担**:
    - **マスター**（行数が決まっていて画面から増減しない表）は **Seeder** で投入し、`$this->seed(XxxSeeder::class)` で呼ぶ。このリポジトリは固定の種別を Enum で持つのでマスター表は無い（`DatabaseSeeder` は手元で画面を触るためのサンプルデータで、テストでは使わない）。
    - **トランザクション系のテストデータ**（users / purchases / sales）は **Factory** で作る（各モデルに Factory を作成。`PurchaseFactory` の `draft()` / `exempt()` のように状態は Factory の state で表す）。
- **テスト本文で `Model::create([...])` を直書きしない**。Factory か Seeder を使う。
- Enum 値はテストコード内で直接参照する（`Kubun::CONSIGN` 等）。
- 認証が必要なテストは `$this->actingAs($user)`（セッションの web ガード）でログイン状態を作る。
    - OK例（ユーザーは setUp で Factory、テスト固有の仕入は各テストの冒頭で Factory）
        ```php
        public function setUp(): void
        {
            parent::setUp();

            // ----- テストユーザー（本人 + 別ユーザー、Factory）-----
            $this->user = User::factory()->create();
            $this->otherUser = User::factory()->create();
        }

        // テスト固有のデータは各テストの冒頭で
        $purchase = Purchase::factory()->create(['user_id' => $this->user->id, 'price' => 3000]);
        $draft = Purchase::factory()->draft()->create(['user_id' => $this->user->id]);
        ```
    - NG例（テスト本文で Model::create を直書き）
        ```php
        // ← Factory / Seeder を使わず直書きするのは NG
        $purchase = Purchase::create([
            'user_id' => $this->user->id,
            'transaction_date' => '2026-07-01',
            'item_name' => 'カメラ',
        ]);
        ```

### 画面（Inertia）のテスト

- **URL はルート名で書く**（公式スターターキットと同じ）：`$this->get(route('ledger'))` / `$this->from(route('home'))->put(route('purchases.update', ['id' => $purchaseId]), ...)` / `assertRedirect(route('login'))`。クエリも `route('ledger', ['from' => '2026-07-01'])` の引数で渡す。文字列の URL（`'/ledger'`）は書かない。
    - data provider の中では `route()` を呼べない（アプリの起動前に評価される）ので、ルート名とクエリを持たせてテストの本体で `route($routeName, $query)` にする。
    - 例外：存在しない URL を試すテスト（`'/no-such-page'`）、テストの中だけで作るルート（`'/_test/expired-page'`）、Laravel 本体のヘルスチェック（`'/up'`）は文字列のまま。
- 画面の表示は `$this->get(route('ledger'))->assertInertia(fn(Assert $page) => $page->component('ledger')->where('totalCount', 2)->etc())`。props の名前（1段目）は camelCase、中身は snake_case で確かめる（`use Inertia\Testing\AssertableInertia as Assert;`）。
- 値1つずつは `assertInertiaProp('ledgerList.0.record_type', 'payout')` / 件数は `assertInertiaCount('ledgerList', 2)`（`tests/TestCase.php` のマクロ）。
- 登録・更新・削除は `$this->from(route('home'))->post(...)` → `assertRedirect(route('home'))` と DB の状態。入力エラーは `assertSessionHasErrors('item_name')`（セッションのキーは snake_case）、完了の案内は `assertInertiaToast('...')`（`TestCase` の補助。フラッシュの `toastData` を確かめる）。
- 送る値は画面と同じ snake_case にする（画面・サーバーで同じキー名）。
- 画面からの操作（`withHeader('X-Inertia', 'true')`）で 404 等になったときの戻し方、部分リロード（`reloadOnly`）、未ログイン（`assertRedirect(route('login'))`）も必要に応じて確かめる。

### 画面の E2E（Playwright）

- `tests/e2e/`。`npm run build` のあと `npm run e2e`（専用の DB・storage を `.e2e/` に作って `php -S` で起動する）。
- 文言・役割（role）・ラベルで要素を探す（実装のクラス名に依存しない）。テストごとに新規ユーザーを登録してデータを分ける。
- CI でだけ、失敗したテストを1回やり直す（`playwright.config.ts` の `getRetryCount()`）。手元では0回にして、不安定なテストに気づけるようにする。
