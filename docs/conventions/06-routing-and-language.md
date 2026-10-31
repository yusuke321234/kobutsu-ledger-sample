# ルーティング規則

## 画面とルート（Laravel の routes/web.php）

詳細は [10-api-conventions.md](10-api-conventions.md)。

- 画面・操作のルートはすべて `routes/web.php`（Inertia）。React 側にルーターは置かない。
- `web.php` は **機能ごとに、画面（GET）とその操作（POST / PUT / DELETE）・ファイルの取得を並べて書く**（Laravel の標準。同じ機能の表示と操作が隣り合う）。
    - 例：仕入なら一覧画面 `GET /` の直後に `POST /purchases`・`PUT /purchases/{id}`・`DELETE /purchases/{id}`
    - `guest` / `auth` のグループの中で機能ごとに並べる。どのルートにも当たらない URL の `Route::fallback` は最後に置く
    - コントローラも画面用・操作用に分けない（1つの機能の表示と操作は同じコントローラ。例：`PurchaseController` の `getList` / `register` / `update` / `delete`）
- パスは kebab-case・小文字（`/ledger/pdf`, `/user/office`）。個別リソースはパスパラメータ（`/purchases/{id}`、`/photos/{id}`）、一覧の絞り込みはクエリ（`/?status=sold&q=カメラ`）、動詞の操作はサブパスにする。
- **ルート名は URL をもとに付ける**（先頭の `/` を取り、途中の `/` は `.`。トップ `/` だけは `home`）。
    - 例：`/settings` → `settings`、`/ledger/pdf` → `ledger.pdf`、`/photos/{id}` → `photos.show`
    - 同じ URL に操作が複数あるときは後ろに `.動作` を付ける（`/purchases/{id}` → `purchases.update` / `purchases.delete`）。操作だけの URL で、URL に動詞がないときも `.動作` を付ける（POST `/purchases` → `purchases.register`、PUT `/user/office` → `user.office.update`。URL に動詞があれば付けない：POST `/logout` → `logout`）。画面と同じ URL へのフォームの送信は `.submit`（POST `/login` → `login.submit`、`/register` → `register.submit`）
    - Wayfinder はハイフン入りの名前を camelCase にする（`foo-bar` → `import { fooBar } from '@/routes'`）。`as` で名前を付け直さなくてよい形にする
- **フォームごとに送信先を分ける**（公式キットと同じ。プロフィールは `PATCH /settings/profile`、パスワードは `PUT /settings/password`）。1つの送信先に複数のフォームを送らせない。
    - 例：設定の氏名は `PUT /user/name`（`user.name.update`）、営業所は `PUT /user/office`（`user.office.update`）。
    - 送信先を共用すると、検証の必須項目を満たすために別のフォームの項目まで送らせることになり（営業所の保存で氏名も送る）、Controller もどのフォームから来たかを推測することになるため。
- **すべてのルートに名前を付ける**（公式スターターキットと同じ。テストや PHP からは `route('名前')` で参照し、文字列の URL を書かない）。画面側からフォームの送信先を参照するときは `@/actions`（コントローラ）、画面へのリンクは `@/routes`（名前）。名前が無いのは `Route::fallback` だけ。
- ガード: 認証必須は `auth`、ゲスト専用（ログイン/登録）は `guest`。ログインと登録の送信には `throttle:login` / `throttle:register` を掛ける（上限は `AppServiceProvider::configureRateLimiters()`）。
- **このリポジトリには外部サービスから呼ばれる API が無いので、`routes/api.php` は置かない。** 足すときは `routes/api.php` に置き、コントローラも `app/Http/Controllers/Api/{Domain}/` に分ける（呼ぶのが利用者の画面ではなく外部サービスで、認証の仕方（署名など）も違うため）。
- 画面からの操作は `api.php` に置かない（`api.php` のルートにはセッションと CSRF 対策が効かず、ログイン中でも未ログイン扱いになる）。

# 言語方針

- **コード (クラス名・メソッド名・変数名・ルート)**: 英語
- **コメント・ドキュメント**: 日本語可
- **テストメソッド名**: 英語
