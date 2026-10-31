# 画面とサーバーの境界規約（Inertia）

画面（React）とサーバー（Laravel）は **Inertia** でつなぐ。REST API は作らず、Controller が画面に props を渡し、操作は元の画面へリダイレクトで戻す。外部から呼ばれる API も無いので、`routes/api.php` は置かない（06-routing-and-language）。

## ルート設計（`routes/web.php`）

- 機能ごとに、画面（GET）とその操作・ファイルの取得を並べて書く（詳細は [06-routing-and-language.md](06-routing-and-language.md)）。

- 画面の表示は GET：`/`（在庫）、`/ledger`、`/settings`、`/login`、`/register`。
- 操作は動詞に合った HTTP メソッド：`POST /purchases`、`PUT /purchases/{id}`、`DELETE /purchases/{id}`、`POST /sales`、`PUT /user/office`。
- ファイルを返すものも GET のルート（`/ledger/pdf`、`/ledger/csv`、`/photos/{id}`）。本人確認はセッションで行う。
- パスは kebab-case / 小文字。**すべてのルートに URL をもとにした名前を付ける**（画面側は Wayfinder の `@/routes` で参照する。付け方は 06-routing-and-language）。
- **PHP のコードでも URL は文字列で書かず `route('名前')` で作る**（リダイレクト `redirect()->route('home')`・ページごとの移動 `Inertia::location(route('login'))`）。画面へ値として渡す URL（写真の `url` など）は第3引数を `false` にして相対 URL にする（`route('photos.show', ['id' => $id], false)` → `/photos/1`）。
- 全ルートに掛けるミドルウェア（`InitialLog` / `LogValidationError` / `HandleInertiaRequests` / `AddLinkHeadersForPreloadedAssets`）は、公式と同じく `bootstrap/app.php` の `$middleware->web(append: [...])` で `web` グループに足す。`routes/web.php` をグループで包まない。
- どのルートにも当たらない URL は、`routes/web.php` の最後の `Route::fallback` で 404 画面にする（`web` グループを通るのでログイン状態を使って表示できる）。

## Controller の返し方

| 種類 | 返すもの |
|---|---|
| 画面の表示 | `Inertia::render('ledger', [...])` |
| 登録・更新・削除 | `back()`（完了の案内は `Inertia::flash(FlashConsts::TOAST_DATA, Toast::success('...'))`） |
| 別の画面へ移す | `redirect()->route('login')` 等 |
| ページごと開き直す | `Inertia::location($url)`（セッション切れでログイン画面を開き直す） |
| ファイル | `response($pdf, 200, [...])` / `response()->file(...)` |
| 対象が無い・権限なし | `abort(404, '利用者向けの文言')` / `abort(403, ...)` |

- **props は必要な時だけ計算する**：`fn() => ...` で包む（部分リロードで要らない props は評価されない）。一覧と件数は1回の取得を共有する（`PurchaseController::getList()` の `$getListResult`）。
- 「もっと見る」の一覧は `Inertia::merge(fn() => ...)->matchOn('id')`（後ろに継ぎ足し、同じ id は置き換え）。
- 全画面で使うもの（ログインユーザー）は `HandleInertiaRequests::share()` の `auth`。相手方チップの履歴は `Inertia::optional()`（「＋仕入」を開いた時だけ部分リロードで取りに来る）。共有 props も `fn() =>` / `Inertia::optional()` で遅延評価にする。

## キーの書き方：props の名前は camelCase、中身と入力は snake_case（公式スターターキットと同じ）

公式は、**画面ごとに自分で付ける名前**（`canResetPassword` / `passwordRules` / `sidebarOpen`）は camelCase、**DB から来るデータの中身**（モデルの列・`created_at_diff`）と**フォームで送る項目名**（`password_confirmation`）は snake_case で書く。props の名前は React 側でそのまま JS の変数（引数）になるので JS の書き方に、データは DB のまま変換せずに渡す、という分け方。変換のコードは置かない。

- **判断のしかた**：`Inertia::render()` と共有 props（`HandleInertiaRequests::share`）の**1段目のキーだけ camelCase**。2段目より下（中身）は snake_case のまま。
    - 例：`purchaseList` / `totalCount` / `pageSize` / `filterData` / `hasPurchase` / `ledgerList` / 共有の `counterpartyList` と `auth`（オブジェクト1つは `xxxData`・配列は `xxxList`。09-frontend-style の命名と同じ）
    - 中身は snake_case：`purchaseList[0].item_name` / `purchaseList[0].sale_data` / `auth.user_data`
- **名前を付け替えるのは Controller だけ**：Service / Repository の配列キーは snake_case のまま（02-naming-conventions）。Controller が渡すときに `'purchaseList' => ... $getListResult()['list']`、`'totalCount' => ... $getListResult()['total_count']` のように付ける。
- **画面から届く入力**：snake_case で送る（`item_name` / `current_page`）。Controller / FormRequest はそのまま受ける。クエリ（`?current_page=2&page_size=20`）も同じ。
    - 例：在庫画面は props `pageSize` を受け取り、「もっと見る」ではクエリ `page_size` として送り返す。
- **検証エラー**：セッションのキーがそのまま画面へ届く（`form.errors.item_name`）。フォームの項目名と一致する。
- **フラッシュのキー**は props の名前と同じ扱いで camelCase（`FlashConsts::TOAST_DATA`＝`toastData`（`{ type, message }` のオブジェクト））。画面側の型は `resources/js/types/global.d.ts`。
- **トーストは種類付きで送る**：`Inertia::flash(FlashConsts::TOAST_DATA, Toast::success('削除しました。'))` ／ 失敗は `Toast::error(...)`（`App\Support\Toast`・種類は `ToastConsts`）。画面は完了＝墨色、失敗＝赤の帯で出す。
- JS の変数・関数・引数・React の部品に渡す props（`onClose` / `purchaseList`）は camelCase。データの中身を取り出して変数に入れるときも `const saleData = purchaseData.sale_data` のように camelCase の変数にする。
- **型**：**ID・区分コードは string**（DB は int でも props では string）／**金額・数量は int**。

## ページングと一覧の絞り込み

- 一覧画面は `current_page`（1始まり）・`page_size` を**任意のクエリ**で受ける（未指定は 1 と `PageConsts::DEFAULT_PAGE_SIZE`＝20）。`page_size` は 100 を上限にする。
- props は **`xxxList`（配列）と `totalCount`（総件数）**、あわせて `filterData`（現在の絞り込み）と `pageSize` を渡す。
- 絞り込みの値が不正なら、FormRequest の `getRedirectUrl()` で**クエリを外した一覧**へ戻す（同じ失敗を繰り返さない）。

## 検証とエラー

- **検証失敗**：Laravel 標準のまま（FormRequest は `rules()` と `messages()` だけ書く）＝**元の画面へリダイレクトし、エラーをセッションに載せる**（JSON を求める要求には 422）。失敗の理由は `LogValidationError` ミドルウェアが1か所でログする（`failedValidation()` は上書きしない）。
- **画面からの操作（`X-Inertia` 付きの POST/PUT/DELETE）が 403 / 404 / 500 / 503** になったら、元の画面へ戻して**失敗のトースト（赤）**で理由を伝える（`abort()` の文言をそのまま使う。5xx は中身を出さない）。
  あわせてエラー `request`（`ErrorConsts::REQUEST`）を載せる＝画面側は `onError` になり、成功時の処理（モーダルを閉じる・連続登録の入力を空にする等）が走らない。
- **画面遷移（GET）の 403 / 404** はエラー画面（`pages/error.tsx`）。500 / 503 は `APP_DEBUG=false` のときだけエラー画面（開発中は Laravel の詳細なエラー画面）。
- **419（ページの有効期限切れ）** は元の画面へ戻して「もう一度お試しください」（エラー `request` 付き）。
- **セッション切れ** はログイン画面へ。画面を開いたまま切れた場合（`X-Inertia` 付き）は「再度ログインしてください。」をフラッシュし、`Inertia::location()` でログイン画面を**ページごと**開き直す（途中の操作を成功扱いにしない。画面遷移なら行き先を `url.intended` に残す）。
- **レート制限** はフォームのメールアドレス欄のエラーとして戻す（JSON を求める要求には 429）。
- 実装は `AppServiceProvider::configureInertiaExceptions()` / `configureRateLimiters()`。

## 認証・認可

- 認証は Laravel 標準のセッション（web ガード）。ルートは `auth` / `guest` ミドルウェア。FormRequest に `authorize()` は置かない。
- 他人の仕入・売却・写真は、Repository が `user_id` で絞るので「見つからない」扱いになる（更新・削除・写真の取得は `abort(404)`、売却の登録は検証エラー＝`RegisterSaleRequest` の `Rule::exists(...)->where('user_id', ...)`）。

## ログ

- **書き先はリクエストごとに分ける**：`storage/logs/年/月/日/web/コントローラ/メソッド.log`（日本時間の日付）。
    - 例：`POST /purchases` → `web/purchase/register.log`、`PUT /purchases/{id}` → `web/purchase/update.log`（URL が同じでもメソッドで分かれ、ID ごとのファイルも作らない）
    - コントローラ名は `Controller` を除いて kebab-case（`PurchasePhotoController` → `purchase-photo`）、メソッド名も kebab-case（`getList` → `get-list`）
    - 存在しない URL は `web/not-found.log`、artisan コマンドは `console/コマンド名.log`、ルートが決まる前のエラー等は `web/other.log`
    - 実装は `config/logging.php` の `single`（custom ドライバ）→ `App\Logging\RequestPathLogHandler`。書き先は**ログを書く時**に決める（設定ファイルで決めると本番の `config:cache` で固定されるため）。書けなければ従来の `laravel.log` に書く
    - PHPUnit ではログを出さない（`phpunit.xml` の `LOG_CHANNEL=null`）
- `web` グループの `InitialLog`（IP / ホスト）がリクエスト開始マーカーを出す。
- Controller は `// get` → `// log` → `// execute` → `// return`（07-architecture）。**個人情報は記録しない**（パスワード・相手方の住所・職業・年齢）。
