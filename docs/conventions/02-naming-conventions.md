# 命名規則

## 必須ルール

### クラス名 (PascalCase)

| 種別                 | パターン                 | 例                                              |
| -------------------- | ------------------------ | ----------------------------------------------- |
| Controller           | `XxxController`          | `PurchaseController`, `SaleController`          |
| Model                | `Xxx`（単数形）          | `Purchase`, `Sale`, `PurchasePhoto`             |
| Service              | `XxxService`             | `PurchaseService`                               |
| Service Interface    | `XxxServiceInterface`    | `PurchaseServiceInterface`                      |
| Repository           | `XxxRepository`          | `PurchaseRepository`                            |
| Repository Interface | `XxxRepositoryInterface` | `PurchaseRepositoryInterface`                   |
| Form Request         | `XxxRequest`             | `GetPurchaseListRequest`, `RegisterSaleRequest` |
| Enum                 | `Xxx`                    | `Kubun`, `SalesChannel`, `VerificationMethod`   |
| Factory              | `XxxFactory`             | `PurchaseFactory`, `UserFactory`                |
| Seeder               | `XxxSeeder`              | `DatabaseSeeder`                                |
| 定数クラス           | `XxxConsts`              | `PageConsts`, `FlashConsts`                     |

- **Form Request 名は、それを使う Controller メソッドの動詞に必ず合わせる**：`register()` → `RegisterXxxRequest`、`update()` → `UpdateXxxRequest`、`delete()` → `DeleteXxxRequest`、`getList()` → `GetXxxListRequest`。**別の Request を意図的に流用する場合のみ例外**（その理由をコメントで明示）。

### メソッド名 (camelCase)

- 動詞ベース。**クラス（Service / Controller / Repository）が表すドメイン名詞をメソッド名で繰り返さない**。呼び出し側で意味が分かるため `$purchaseService->getList()` と書く（× `getPurchaseList()`）
- よく使う動詞: `get*`, `register*`, `update*`, `delete*`, `export*`（Repository は DB の言葉で `create*`。07-architecture）
- 曖昧・同じ動詞が複数あるときだけ修飾語を足す: `getListByUserId()`, `countByPurchaseId()`, `getForLedger()` 等
- 例: `PurchaseService::getList()` / `PurchaseService::register()` / `SaleService::update()`（× `getPurchaseList()` / `registerPurchase()` / `updateSale()`）
- **Controller の操作のメソッドは、何をするかの動詞にする。画面の名前や利用者の状況の名前にしない**（`updateOffice()`。× `settings()`）。呼ぶ Service のメソッドと同じ名前にそろえる（`AuthController::updateOffice()` → `AuthService::updateOffice()`）
    - 画面を表示するメソッドは、何を見せる画面かで2通りに分ける
        - データを取ってきて見せる画面＝`get*`（何を取るか。`PurchaseController::getList()`＝在庫 / `LedgerController::getList()`＝古物台帳）
        - 入力フォームなど、取ってくるデータが主役でない画面＝`show` ＋画面名（`showLogin()` / `showRegister()` / `showSettings()`）。`settings()` のように画面の名前だけにしない
    - 画面の URL とルート名は画面名のまま（`/settings` → `settings`、`/ledger` → `ledger`）。操作の URL は、画面の URL を流用せず、何をするか・何を扱うかの名前にする（PUT `/user/office` → `user.office.update`。× PUT `/settings`）
    - 例外：ファイルを1件返す `show()`（`PurchasePhotoController::show()`＝写真の配信）は Laravel の標準の名前のまま

```php
$purchaseService     // インスタンス
$purchaseList        // ローカル変数
$totalCount          // ローカル変数
```

### 配列キー (snake_case)

- Service / Repository / Controller 内の配列キーは **snake_case**（DB の列名と同じ表記）。画面から届く入力とデータの中身も snake_case のまま（画面側の型 `resources/js/types/` も同じキー名）。
- **例外：画面へ渡す props の名前（`Inertia::render()` と共有 props の1段目のキー）とフラッシュのキーは camelCase**（`purchaseList` / `totalCount` / `hasPurchase`。公式スターターキットと同じ。10-api-conventions.md）。

```php
'total_count', 'item_name', 'raw_gross_profit', 'record_type'
```

### データ型の命名

- 配列は原則、「~List」
- foreachで配列（「~List」）を分解したものは「~Data」
- 数値は原則、「~Count」
- 名前には Num ではなく Count または Index を使用する
- **レスポンス配列のキーも同様**：単一のオブジェクト（連想配列）を値に持つキーは `xxx_data`（例: `sale_data`＝仕入に付く売却の要約 / `user_data`＝ログインユーザー）、オブジェクトの配列を値に持つキーは `xxx_list`（例: `photo_list`）。供給する変数も同様（`$saleData` / `$photoList`、`foreach ($purchaseList as $purchaseData)`）。
    - **例外**：一覧のページング外枠 `{ total_count, list }` の `list` は据え置く（10-api-conventions が規定する外枠のため）。

### ID・数値の型

- **ID 系（`id` / `*_id` の主キー・外部キー）は、Laravel / React 内および画面との受け渡しで string として扱う**。DB カラムが int でも、コード・props では string にする（識別子であり計算対象ではない。JS の数値精度問題も回避）。
    - レスポンス整形例: `'purchase_id' => (string)$saleData['purchase_id']`。null 許容の値は if で分けて `(string)` する（インライン三項でキャストしない。`PurchaseService::shapePurchase()` の `verification_method`）
    - GET の query から受けた ID は `$request->validated()` が string で返すので、そのまま string｜null として扱う
- **int を使うのは原則「四則演算する値」のみ**（ページ番号・件数・金額・数量 等）。
- 固定種別の区分コード（`kubun` / `verification_method` / `channel`）も **string**（`'1'` / `'2'`）で扱う。Enum は数字の文字列を値に持つ string バック（`enum Kubun: string { case BUY = '1'; ... }`）にし、DB の列は整数（`tinyInteger`）で持つ。区分コードは識別子であり計算しないため、ID と同じく string にそろえる（07-architecture）。

---

実装時の命名観点チェックリストは [90-review-checklist.md](90-review-checklist.md) を参照。
