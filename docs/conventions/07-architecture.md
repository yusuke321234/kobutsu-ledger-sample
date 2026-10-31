# アーキテクチャルール

## 基本
1. ビジネスロジックは Service レイヤーに書く (Controller には書かない)
2. DB アクセスは Repository 経由で行う
3. Service・Repository は必ずインターフェース (Contract) を定義する（`app/Contracts/`。実装との対応は `AppServiceProvider::register()` でバインドする）
4. DI はコンストラクタインジェクションを使う（**プロパティを宣言し、コンストラクタで代入する明示形**で書く。コンストラクタプロモーション `__construct(private X $x)` は使わない）
    ```php
    class PurchaseService implements PurchaseServiceInterface
    {
        private PurchaseRepositoryInterface $purchaseRepository;

        public function __construct(PurchaseRepositoryInterface $purchaseRepository)
        {
            $this->purchaseRepository = $purchaseRepository;
        }
    }
    ```
5. バリデーションは Form Request クラスに書く

## Repository のメソッド名と、新規か更新かの判別
・**Repository は DB の言葉で名前を付ける**（Eloquent と同じ）：作成は `create`、id を指定した更新は `update`、削除は `delete`、読み取りは `get…` / `count…`。id 以外のキーで更新・取得するときだけ後ろに `By…` を付ける（`getListByUserId` / `countByPurchaseId`）。
    - Controller・Service は画面の操作の名前（`register` / `update` / `delete`）のまま。業務の言葉は `register`、DB の言葉は `create` と、層ごとに分ける（`PurchaseService::register()` → `PurchaseRepository::create()`）
    - 1つのメソッドで作成と更新を兼ねない（`register('0', …)` のように id `'0'` で新規を表す書き方はしない）。Repository には用途別の更新メソッドを作らず `update` にまとめる（用途ごとの名前＝`updateName` / `updateOffice` は Service に置く。`UserRepository::update()` を `AuthService::updateName()` / `updateOffice()` から呼ぶ）
・**1件の取得は `first()` の1回で取り、`?->toArray()` で返す**（見つかれば配列、なければ null）。`count()` で件数を確かめてから `first()` で取り直さない（問い合わせが2回になり、間で行が消えると `null->toArray()` で落ちる）。 **🤖 `composer phpstan` で確かめる**（自作ルール `tests/PHPStan/Rules/NoCountThenFirstRule.php`。Repository の `->count() > 0` を指摘）
    ```php
    $purchase = $this->purchase->where('id', $id)->where('user_id', $userId)->first();

    //
    return $purchase?->toArray();
    ```
・**Repository は言われたとおりに読み書きするだけで、新規か更新かも、どの項目を更新してよいかも決めない**（どちらも業務のルールなので Service で決める）。
    - 呼ぶ側で決まっている場面（`POST /sales` と `PUT /sales/{id}` のようにルートで分かれている）は、判別せずに `create` / `update` を呼ぶ
    - データ次第で変わる場面（記録があれば更新、無ければ作成、のような処理）は、Service が `get…` で確かめてから `create` / `update` を呼び分ける。新規のときだけ別の処理をする、などの違いも Service に書く。仕入の削除で「売却済みなら削除しない」を、`PurchaseService::delete()` が `getData()` で確かめてから決めるのも同じ考え方
    - 更新する項目は、Controller が `validated()` から組み立てる配列で決まる（例：`updateName()` は `family_name` / `given_name` だけの `$nameData` を作る）。Service で同じ項目に絞り直さない（`Arr::only` は使わない）。画面から余計な項目を送られても、Controller が項目を決めて配列を作るので Repository まで届かない

## Controller
・「public function xxx()」の中身は **`// get` → `// log` → `// execute` → `// return`** の4セクションを必ず記載する（処理が無いセクションも省略せず `//` を置く）。
    - **`// get`**：**`$request->validated('xxx')` で検証済みの値だけを取得**し（公式スターターキット・Laravel のドキュメントと同じ。`input()` は使わない。`rules()` に書き忘れた項目は null になり、テストで気づける）、真偽値・有無は `$request->safe()->boolean('xxx')` / `$request->safe()->has('xxx')`、ファイルも `$request->validated('photo')`。FormRequest を通らない GET の画面のクエリだけは `$request->query('xxx')`。取得した値は**変数・条件配列（`$xxxData`）に整形**する。**必須項目は明示的に型キャストする**（ID・文字列は `(string)`、四則演算する数値は `(int)`）。`$request->safe()->boolean('xxx')` のように型をそろえて返すものにも、読み手に型が分かるよう `(bool)` を付ける（`(bool)$request->safe()->boolean('is_draft')`）。**任意（nullable）項目は FormRequest で nullable 検証済みなら `$request->validated('xxx')` を直接代入してよい**（検証で string｜null が保証され、ID 系もフロントが string で送る規約のため。`if`-`(string)` ブロックは作らず Controller の肥大化を防ぐ）。ただし**一覧フィルタの値**は未指定＝`''` 扱いにするため `(string)` で空文字に寄せてよい（Repository 側で `!== ''` 判定。例：検索語 `q`）。なおレスポンス整形（Service の `shapeXxx`）側の null 許容の値は従来どおり `if` で分けて `(string)` する（[02-naming-conventions.md](02-naming-conventions.md)）。
    - **`// log`**：`// get` で取り出した値を `Log::info()` で出力（スカラーはそのまま、配列は `print_r($x, true)`）。**パスワード等の機密値と、相手方の住所などの個人情報はログに出さない**（マスクしてから出す。`AuthController::login()` のパスワード、`PurchaseController::maskForLog()` の相手方の住所・職業・年齢）。
    - **`// execute`**：Service のメソッドに **`// get` で整形した値を渡す**。`$request` をそのまま Service に渡さない（Service を Http 層に依存させない）。
    - **`// return`**：結果を返す（このリポジトリでは画面の表示＝`Inertia::render()`、登録・更新・削除＝`back()`、見つからない＝`abort(404)`。[10-api-conventions.md](10-api-conventions.md)）。
    - OK例（値を取り出す → ログ → 値を渡す。`SaleController::delete()`）
      ```php
      public function delete(Request $request, string $id): RedirectResponse
      {
          // get
          $userId = (string)$request->user()->id;

          // log
          Log::info("user_id: {$userId}");
          Log::info("sale_id: {$id}");

          // execute
          $deleted = $this->saleService->delete($userId, $id);

          // return（対象が無ければ404）
          if ($deleted === true) {
              Log::info("売却を論理削除しました。 sale_id: {$id}");
          } else {
              Log::info("削除対象が見つからないため404を返します。 sale_id: {$id}");

              //
              abort(404, '対象の売却が見つかりません。');
          }

          //
          Inertia::flash(FlashConsts::TOAST_DATA, Toast::success('売却を取り消しました。'));
          return back();
      }
      ```
    - 処理が無いセクションは `//` のみ
      ```php
      // get
      //

      // log
      //
      ```

## Consts (定数クラス)
・**定数 (`const ...`) は `App\Consts\XxxConsts` クラスに集約する。 Service / Controller / Repository などのドメインクラスに `private const` / `public const` を置かない。**
    - 「設定値・マジックナンバー」を 1 箇所 (`App\Consts/`) に集約することで、横断的な見直しがしやすくなる
    - Service 内に閉じた定数は「使う側だけが知っていれば良い」ように見えるが、レビュー時に「他クラスからも見えるべきか？」の判断を都度迫られる。最初から Consts に置けば迷いがなくなる
    - 配置先の Consts クラス名は、扱う対象の名前 +「Consts」 (例: `PageConsts`（一覧のページング）、`PurchasePhotoConsts`（仕入の写真）)
    - NG例（Service 内に const を置いている）
        ```php
        class PurchasePhotoService implements PurchasePhotoServiceInterface
        {
            private const MAX_COUNT = 5;

            public function register(string $userId, string $purchaseId, UploadedFile $file): array
            {
                $count = $this->purchasePhotoRepository->countByPurchaseId($purchaseId, $userId);
                if ($count >= self::MAX_COUNT) {
                    // ...
                }
            }
        }
        ```
    - OK例（Consts クラスに集約）
        ```php
        // App/Consts/PurchasePhotoConsts.php
        class PurchasePhotoConsts
        {
            /** 1つの仕入に付けられる写真の上限 */
            public const MAX_COUNT = 5;
        }

        // App/Services/PurchasePhotoService.php
        class PurchasePhotoService implements PurchasePhotoServiceInterface
        {
            public function register(string $userId, string $purchaseId, UploadedFile $file): array
            {
                $count = $this->purchasePhotoRepository->countByPurchaseId($purchaseId, $userId);
                if ($count >= PurchasePhotoConsts::MAX_COUNT) {
                    // ...
                }
            }
        }
        ```

・**`App\Consts\XxxConsts` には定数 (`public const ...`) のみを置く。 関数 (`public function` / `public static function`) を置かない。**
    - 「定数の集合」と「ロジック」を同じクラスに同居させると役割が曖昧になる
    - **固定のステータス・種別と、その表示名（label）の解決は PHP Enum に持たせる**。Enum はメソッド（`label()` 等）を持ってよい。Consts クラスとは役割が別。
    - NG例（Consts クラスに switch を持つ static function がある）
        ```php
        class KubunConsts
        {
            public const BUY = '1';
            public const CONSIGN = '2';

            public static function label(string $value): string
            {
                switch ($value) {
                    case self::BUY:     return '買受け';
                    case self::CONSIGN: return '委託';
                    // ...
                }
            }
        }
        ```
    - OK例（固定種別は Enum、表示名は Enum の label() に持たせる。`App\Enums\Kubun`）
        ```php
        // App/Enums/Kubun.php（数字の区分コードを string で持つ。DB は整数で格納）
        enum Kubun: string
        {
            case BUY = '1';
            case CONSIGN = '2';

            public function label(): string
            {
                return match ($this) {
                    self::BUY => '買受け',
                    self::CONSIGN => '委託',
                };
            }
        }
        ```
    - マジックナンバー/文字列は `XxxConsts`（定数のみ）、固定種別は Enum、と使い分ける
    - **固定種別は「数字の区分コード」で管理し、Enum は string バック**にする（`enum X: string { case A = '1'; ... }`）。DB カラムは整数（`tinyInteger`）で区分コードを格納し、Service が画面へ渡すときに `(string)` で文字列にそろえる（`PurchaseService::shapePurchase()` の `kubun` / `verification_method`）。検証は `Rule::enum(Xxx::class)`、表示名は `Xxx::from($code)->label()`（`LedgerService` の帳票の表示名）。画面側は `resources/js/consts/` に同じ区分コードと表示名を持つ（`KUBUN_OPTION_LIST` 等）。
