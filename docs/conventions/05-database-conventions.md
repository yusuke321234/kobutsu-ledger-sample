# データベース規則

テーブルの定義はマイグレーション（`database/migrations/`）が正。本ファイルは命名と実装上の決まり。

## テーブル名

- **snake_case・小文字・複数形**（`purchases`, `sales`, `purchase_photos`, `users`）。プレフィックス（`t_` 等）は付けない。
- 中間テーブルは **単数形_単数形**（Laravel の標準。例：`role_user`）。
- 固定ステータス・種別は **DB テーブル化しない**（PHP Enum で管理。07-architecture）。

## カラム名 (snake_case)

- 主キー・外部キー: `id`, `user_id`, `purchase_id`
- **タイムスタンプ: `created_at` / `updated_at` は DATETIME 型で持つ。マイグレーションは `$t->datetimes()` を使う（`$t->timestamps()` は使わない）。** MySQL の `TIMESTAMP`（`timestamps()` / `softDeletes()` が生成）は **2038年問題**（1970〜2038）を抱えるため、`DATETIME`（1000〜9999）で統一する。その他の日時カラムも `$t->datetime('xxx')` を使う（`timestamp('xxx')` は使わない）。
- 真偽値: `is_*` / `has_*` を基本（`is_draft`, `is_exempt`）。Enum で表せる区分は Enum を使う（`kubun`, `verification_method`, `channel`）。

## 削除（ソフトデリート）

- **Model は公式（Laravel 13）の書き方にそろえる**：代入を許す列は `#[Fillable([...])]`、隠す列は `#[Hidden([...])]` をクラスに付ける（`protected $fillable` / `$hidden` は使わない）。型の変換は `protected function casts(): array`（`protected $casts` は使わない）。
- **本番では DB を消すコマンドは使えない**（`DB::prohibitDestructiveCommands`。`migrate:fresh` / `migrate:refresh` / `migrate:reset` / `migrate:rollback` / `db:wipe`）。公開済みのマイグレーションは書き換えず・戻さず、変更は**打ち消すマイグレーションを新しく足す**。手元（local）とテストでは従来どおり使える。
- **日付は書き換えできない型（`CarbonImmutable`）で返る**（`now()` と Model の日付の列。`Date::use`）。`$date->addDay()` は新しい値を返すだけなので、必ず戻り値を使う（`$end = $start->addDay()`）。
- **原則、全テーブルでソフトデリート（論理削除）を使う。** マイグレーションに `$t->softDeletesDatetime()`（`deleted_at` を DATETIME で追加。2038年問題回避）、Model に `use SoftDeletes;` を付与する。
- `delete()` は `deleted_at` をセットするだけで物理削除しない。通常のクエリ（`get` / `where` 等）は Eloquent が自動で `deleted_at IS NULL` を付けるため、削除済みは除外される（Repository 側で特別な対応は不要）。
- 物理削除（`forceDelete` や、論理削除を持たないテーブル）は、法令対応・容量・個人情報削除要請など**明確な理由がある場合のみ**。その理由をコメントで明示する。
    - 例：`purchase_photos` は論理削除を持たない（写真は法定の記載事項ではないので、利用者が消したら行もファイルも消す。理由はマイグレーションのコメントに書く）。台帳の元になる `purchases` / `sales` は法定3年保存のため論理削除のみ。

## 正規化

- 第3正規形（3NF）以上。同一データを複数テーブルに重複保持しない（在庫の状態は `sales` の有無と `is_draft` から導出し、`purchases` に状態のカラムを持たない）。

## クエリの組み方

**基本は「1テーブル = 1Repository」。各テーブルを別々のクエリで取得し、PHP 側（Service）で結合する。**
- Repository の責務が明確になり、インデックスが効きやすい。Repository が他テーブルを覗かない。
- 結合先テーブルの行数が少ない場合は、PHP 側で map を作ってループするほうが見通しが良い。

**JOIN は禁止ではなく「控えめに」**:
- 大量データを SQL 側で絞り込まないと PHP メモリが破綻する箇所では JOIN を使ってよい。
- JOIN を使う場合は、その理由（なぜ PHP 側結合でなく JOIN なのか）をコメントで明示する。

- NG例（Repository で leftJoin して、別テーブルのカラムまで1本のクエリで取ってくる）
    ```php
    // PurchaseRepository が sales まで覗いて、台帳の行を作っている
    public function getLedgerRowList(string $userId): array
    {
        $list = $this->purchase
            ->leftJoin('sales', 'sales.purchase_id', '=', 'purchases.id')
            ->where('purchases.user_id', $userId)
            ->select('purchases.transaction_date', 'purchases.item_name', 'sales.sale_date', 'sales.sale_price')
            ->get()
            ->toArray();
        return $list;
    }
    ```
- OK例（受入と払出をそれぞれの Repository で別々に取り、Service 側で1表にまとめる。`LedgerService::buildRows()`）
    ```php
    // LedgerService（PHP 側で結合）
    private function buildRows(string $userId, array $filterData, bool $ascending): array
    {
        $from = $filterData['from'];
        $to = $filterData['to'];
        $q = $filterData['q'];

        // 受入（仕入）
        $purchaseList = $this->purchaseRepository->getForLedger($userId, $from, $to, $q);
        $rowList = [];
        foreach ($purchaseList as $purchaseData) {
            $rowList[] = $this->shapeReceiveRow($purchaseData);
        }

        // 払出（売却）
        $saleList = $this->saleRepository->getForLedger($userId, $from, $to, $q);
        foreach ($saleList as $saleData) {
            $rowList[] = $this->shapePayoutRow($saleData);
        }

        // 時系列1表にする
        usort($rowList, function (array $a, array $b) use ($ascending): int {
            // ...（取引年月日で並べる）
        });

        //
        return $rowList;
    }
    ```
