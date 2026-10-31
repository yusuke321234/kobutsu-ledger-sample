# フロントエンド (React + Inertia + TypeScript) コーディングスタイル

**Laravel 公式 React スターターキットの構成に準拠**することを大方針とする（Inertia 3 / React 19 / TypeScript / Tailwind CSS 4 / shadcn/ui / Wayfinder / Vite+）。画面とサーバーの境界規約は [10-api-conventions.md](10-api-conventions.md)。

## 基本方針

| 項目 | 採用 |
|---|---|
| 画面 | **React 19**（関数コンポーネント + Hooks）。**React Compiler** 有効（`useMemo` / `useCallback` は原則書かない） |
| 画面とサーバーのつなぎ | **Inertia 3**。API 層を作らず、Controller が `Inertia::render()` でページに props を渡す |
| ルーティング | Laravel の `routes/web.php`（React Router は使わない）。URL は **Wayfinder** の生成関数で組み立てる |
| フォーム | Inertia の `useForm`（検証エラーは `form.errors` に自動で届く） |
| 状態管理 | サーバーの状態＝ページの props（`usePage`）。画面をまたぐ UI 状態（共通モーダル）＝ `hooks/use-ui.tsx` の Context。画面ローカル＝`useState` |
| UI 部品 | **shadcn/ui**（Radix）を `components/ui/` に置き、見た目は Tailwind のクラスで今の配色に合わせる。アイコンは Heroicons |
| ビルド・Lint・整形 | **Vite+**（`vp build` / `vp check`）。Oxlint（React Hooks ルール込み）+ Oxfmt |
| 言語 | **TypeScript（strict）**。型パズルは避け実用的な型付けに留める |
| CSS | **Tailwind CSS 4**（トークンは `resources/css/app.css` の `@theme`） |
| SSR | 使わない（`config/inertia.php`） |

## ディレクトリとファイル名

- **ファイル名は kebab-case**（`inventory-board.tsx`）、**コンポーネント名は PascalCase**（`InventoryBoard`）。スターターキットと同じ。
- `pages/` ＝ Inertia のページ（Controller の `Inertia::render('inventory')` と 1:1）。`components/<ドメイン>/` ＝ 画面の中身、`components/common/` ＝ 画面横断の部品、`components/ui/` ＝ shadcn/ui（**手を入れない**。lint/整形の対象外）。
- `layouts/` ＝ 永続レイアウト（`app-layout.tsx` / `guest-layout.tsx`。`app.tsx` の `layout` で割り当て、ページ遷移しても状態が保たれる）。
- `hooks/` ＝ 画面横断のロジック、`lib/` ＝ 純粋関数（整形・トースト・楽観的更新）、`consts/` ＝ 区分コード・固定文言、`types/` ＝ 画面が受け取るデータの型。
- **`types/` はドメインごとにファイルを分け、`index.ts` は再エクスポートだけにする**（`auth.ts` / `purchase.ts` / `ledger.ts`。スターターキットと同じ形）。読み込みは常に `import type { Purchase } from '@/types'` で、どのファイルにあるかを呼び出し側に書かない。
- `actions/` `routes/` `wayfinder/` は **Wayfinder の生成物**（Git 管理外。ビルド時に `artisan wayfinder:generate` が作る）。

## ページは薄く保つ（ロジックは component へ）

- **`pages/` には見出し（`<h1>`）・`<Head>`（タイトルと説明）・`components/` の呼び出し・props の受け渡しだけを書く。** state・送信・イベントハンドラは `components/<ドメイン>/` へ集約する。
- **ボード（`xxx-board.tsx`）を置くのは、画面全体で状態を共有するとき**（在庫・台帳＝絞り込み・検索・一覧が同じ状態を使う）。
    - まとまりどうしが状態を共有しない画面（設定＝アカウント・営業所・ログアウト）は、ボードを挟まず、ページにまとまりごとの部品を直接並べる（`pages/settings.tsx` → `components/settings/account-section.tsx` 等。公式の設定ページと同じ形）。
    - このとき、全画面共有の props（`auth`）を `usePage()` で受け取り、必要な部品に props で渡すところまではページに書いてよい（部品の中では `usePage()` を呼ばず、何がどこへ渡るかをページで見えるようにする）。
    - 見出し＋補足＋中身の枠が繰り返すときは、枠を部品にする（`settings-section.tsx`。公式の `HeadingSmall` にあたる）。
- **すべてのページにタイトルと説明（description）を付ける**（公式どおりページごとに `<Head>` で書く）。
    - タイトルは `<Head title="古物台帳">`（タブには「古物台帳 | アプリ名」と出る。`app.tsx` の `title`＝`getDocumentTitle()`）。
    - 説明は `<Head>` の中に `<meta head-key="description" name="description" content="..." />`。`head-key` を付けると画面を移るたびに差し替わる（重複しない）。
    - 最初の HTML の既定の説明は `resources/views/app.blade.php`（`data-inertia="description"` で同じ枠に対応づける）。
    - 説明には、変わりうる情報（件数・日付など）を書かない。
- 一覧系は本文幅を広げる：`Inventory.layout = { width: 'wide' }`（既定は 950px、wide は 1200px）。
- 例外：エラー画面のような**ロジックの無い静的なページ**は、ページ内の静的マークアップのままでよい。
- ✅ OK 例
    ```tsx
    import { Head } from '@inertiajs/react'
    import LedgerBoard, { type LedgerBoardProps } from '@/components/ledger/ledger-board'

    // 古物台帳。ページは見出しとボードを並べるだけ。
    export default function Ledger(props: LedgerBoardProps) {
      return (
        <div>
          <Head title="古物台帳">
            <meta
              head-key="description"
              name="description"
              content="仕入・売却の記録から自動で作られる古物台帳です。"
            />
          </Head>
          <h1 className="px-4 pt-6 text-xl font-semibold text-[#1b1b18]">古物台帳</h1>
          <LedgerBoard {...props} />
        </div>
      )
    }

    Ledger.layout = { width: 'wide' }
    ```

## ファイル内の並び順（PHP 側 03-coding-style の「public → private」と揃える）

- **公開するもの（`export` する型・コンポーネント・関数）を先に書き、そのファイルの中だけで使う関数・コンポーネントは後ろにまとめる。** 交互に並べない（外から使えるものが一目で分かるようにするため）。
- import → モジュール定数（`const SEARCH_DEBOUNCE_MS = 300` 等）→ `export` するもの → ファイル内だけの関数、の順。
    - ファイルの先頭に置くのは、**大文字で書く設定値・固定の一覧**（`SEARCH_DEBOUNCE_MS` / `KUBUN_OPTION_LIST` / `INPUT_CLASS`）。1か所でしか使っていなくても、探しやすさを優先して先頭に置く。 **🤖 `npm run lint:naming` で確かめる**（ファイル直下の定数は大文字）
    - 1つの関数の中でしか使わない**ただの値**は、その関数の中で宣言する（`app.tsx` の `getDocumentTitle()` の中の `const appName`。03-coding-style の「変数の寿命はできる限り短く」）。
- 関数宣言（`function xxx()`）は後ろに置いても先に呼べる（巻き上げ）。アロー関数の定数は巻き上げされないので、後ろに回すときは `function` 宣言で書く。
- **ファイル末尾に出すのは、props・state・フックを使わない関数だけ**（必要な値は引数で受け取る。例：`getGeneralErrorMessage(errors)`）。
- **イベント処理（`form` や state を使うもの）はコンポーネントの中に置く**（外に出すと `form` を毎回引数で渡すことになり、React の標準から外れる）。
- コンポーネントの中は **フック → 表示に使う値 → イベント処理 → `return`（JSX）** の順に並べる。

## 命名（型と変数）— PHP 側（02-naming-conventions）と揃える

- **型（`interface` / `type`）＝ PHP の「クラス / Model」に対応 → PascalCase 名詞**（`User` / `Purchase` / `LedgerRow`）。`UserData` のような型名にはしない。 **🤖 `npm run lint:naming` で確かめる**（PascalCase・`Data` で終わらない）
- **名前の最後の語で、中身の形が一目で分かるようにする**（PHP 側 02-naming-conventions と同じ決まり。画面とサーバーで同じ書き方にする）
    - オブジェクト1つ＝`xxxData`（`userData` / `purchaseData` / `filterData`）。props の名前とサーバーのデータのキーも同じ（props の `filterData`、データの中身の `user_data` / `sale_data`）。`map` で分解した要素も `xxxData`（`purchaseList.map((purchaseData) => …)`）。
    - 配列＝`xxxList`（`purchaseList` / `photo_list`）。複数形（`purchases`）は単数と1文字違いで読み違えやすいので使わない。 **🤖 `npm run lint:naming` で確かめる**（配列の変数・引数は `List` / `_LIST` で終わる。例外は shadcn/ui の `cn(...inputs)`）
    - 件数＝`xxxCount`（`totalCount`。`total` だけだと金額か件数か分からない）、ID＝`xxxId`（`userId` / `user_id`）。
    - 種類・区分の値＝`xxxType` / `xxxCode`（台帳の行の種類 `'receive'` / `'payout'`＝`record_type`）。**値を名詞だけにしない**（`record` だと行のオブジェクトに見える）。型は、文字の種類なら `XxxType`（`ToastType` / `LedgerRecordType`）、数字の区分コードなら `XxxCode`。
    - React・公式キットは `user` のように名詞だけで書くが、このプロジェクトは PHP 側とそろえて形が一目で分かるほうを取る。
- 例外：マップ（辞書）は `~List` にしない（`KUBUN_LABEL`）。真偽値（`hasPurchase` / `processing`）は `is` / `has` / 動詞の形で値と分かるので対象外。ライブラリ・フレームワークの標準名（`children` / `props`・`useForm` の `form`・`usePage()` の `page`・イベントの `event`・共有 props の `auth` / `errors`）と、画面の部品の状態（`saleForm` / `purchaseForm` など UI の名前）は据え置く。
- ID 系（`id` / `*Id`）は string で扱う。
- **「値が無い」は空文字 `''` ではなく `null` で表す**（関数の戻り値・state・ref）。型は `string | null` にし、`!== null` で比べる（例：`getGeneralErrorMessage()` はメッセージが無ければ `null`）。
    - 例外：入力欄の値（`<input value>` と `useForm` の入力値）は `null` を受け取れないので `''` のまま（数値を入力欄へ入れるときは `toInputText()`）。
- **関数名は動詞から始める**（PHP のメソッド名と同じ。02-naming-conventions）。名詞だけの関数名（`inputErrorList`）は、配列の変数名（`xxxList`）と見分けがつかないので使わない。
    - 値を求めて返す＝`get*`（`getInputErrorList` / `getStatusClass` / `getFilterQuery`）
    - 表示用の文字列に整える＝`format*`（`formatYen`）
    - 別の型に変換する＝`to*`（`toNumberOrNull` / `toCodeOrNull`）
    - 真偽を返す＝`is*` / `has*`、処理を行う＝`show*` / `apply*` / `exclude*` 等の動詞
    - イベント処理（クリック・送信で呼ぶ関数）も、何をするかの動詞にする（`cancelSale` / `deletePurchase` / `selectCounterparty` / `submit`）。`on*` は使わない。`handle*` も使わない **🤖 `npm run lint:naming` で確かめる**（自分で定義する関数・関数の変数に `on*` / `handle*` を付けない。props の名前は対象外）
        - ボタンの文言（「×」＝`remove`）ではなく、何を外すかまで名前にする（`removeExistingPhoto`＝保存済みの写真を外す）
        - `on*` は **部品が受け取る props の名前だけ**（`onClick` / `onClose` / `onSelect`。React の慣習）。渡すときは `onSelect={selectCounterparty}` のように「props の名前＝できごと、値＝何をするか」になる
    - 例外：フックは React の決まりで `use*`。shadcn/ui の `cn` は標準の名前のまま。
- **状態（`useState`）の真偽値には対象まで入れる**：「何を〜している最中か」が名前で分かるようにする（`loadingMore`＝「もっと見る」の読み込み中 / `popoverOpen`。`loading` / `open` だけにしない）。日付などの値も同じ（`periodFrom` / `periodTo`。`from` / `to` だけにしない）
    - 例外：部品の中で対象が1つに決まるものはそのままでよい（氏名だけを編集する部品の `editing`、ログアウトボタンの `loggingOut`）。
- `useForm` に渡すフォームデータの型は **`type` で定義する**（`interface` だと Inertia の型制約を満たさないことがある）。
- その型の名前は **`〜Input`**（画面名＋Input。`LoginInput` / `PurchaseInput` / `SaleInput`）。
    - `〜FormData` にしない（型名に `Data` を付けない）。`Fields` のような複数形にしない。
    - `Form` / `Input` だけにしない（Inertia の `<Form>`・shadcn の `Input` 部品・変数 `form` とぶつかる）。
- **フォームの項目を並べるオブジェクトは1項目1行で書く**（`useForm` の初期値・`form.setData({...})`・入力欄を空に戻す `{ ...current, item_name: '', ... }`）。`{` の直後で改行して書く。
    - 整形（Oxfmt）は100文字以内なら1行にまとめるが、`{` の直後に改行があれば縦のまま残す。短くても縦にしておくと、項目の一覧として読め、項目を足したときの差分も1行で済む。
    - モーダルの開閉などフォームの項目でないもの（`{ ...current, open: false }`）は1行のままでよい。

## 分岐スタイル（PHP 側 03-coding-style と揃える）

- **`if` の中身は「OK パターン（肯定文）」、`else` で「NG パターン」を捕まえる。** 早期 return でも同じく `if` を肯定文にし、`else` 側で `return` する。
    ```tsx
    if (auth.user_data !== null) {
      // ok.
    } else {
      return null
    }
    ```
- **値を返す分岐（値選択）は `if / else if / else` で明示的に繋ぐ**（最後の枝も必ず `else`）。JSX の外では三項演算子を使わない（関数に切り出すか、既定値の `let` を if で上書きする）。
- **`if / else if` で並べる条件は、同じもの（同じ変数）を `===` で比べる**（`a === 1` / `a === 2`）。違う内容を判定するときは並べずに、if を入れ子にする。
    ```tsx
    // NG：違う内容（画面名の加工と、画面名＋ログイン状態）を並べている
    if (name.startsWith('auth/') === true) {
      return GuestLayout
    } else if (name === 'error' && getLoggedInUser(page.props) === null) {
      return GuestLayout
    } else {
      return AppLayout
    }

    // OK：同じ変数（pageGroup）を並べ、別の判定（ログイン状態）は入れ子にする
    const pageGroup = getPageGroup(name)
    if (pageGroup === 'auth') {
      return GuestLayout
    } else if (pageGroup === 'error') {
      const user = getLoggedInUser(page.props)
      if (user !== null) {
        return AppLayout
      } else {
        return GuestLayout
      }
    } else {
      return AppLayout
    }
    ```
- 単純な `if` / `if-else` なら、違う変数を `&&` でつないだ条件のままでよい（`if (price !== null && price < 10000)`）。ただし **`if-else-if` に枝が増えるときは、`&&` を並べずに入れ子に分ける**。
- **条件式の中で加工しない**（型変換・DOM の検索・`window.confirm` など）。if の直前で変数に入れてから比べる。
- **条件式の結果を真偽値の変数に1行で代入しない**（`const isEdit = target !== null` は NG）。既定値の `let` を if で上書きするか、そのまま if で分岐する。
- **真偽値は必ず `=== true` / `=== false` を付ける**（パッと見で真偽値と分かるように）。
    - 判定（if・三項演算子・`&&`）：`{processing === true ? '保存中…' : '保存'}`
    - 部品に真偽値を渡すときも付ける：`checked={form.data.remember === true}` / `disabled={form.processing === true}` / `open={cancelOpen === true}`
- JSX の条件表示は `{条件 && (...)}` / 三項演算子でよい。

## Hooks と React Compiler

- Hooks は条件分岐の前に呼ぶ（早期 return より上）。ハンドラ関数も早期 return より上に書く。
- **effect の中で同期的に `setState` しない**（React Compiler が最適化を諦める）。値は描画中に導出するか、イベントハンドラで更新する。
- effect から「最新の props/state を読むだけの関数」を呼ぶときは `useEffectEvent` を使う（依存配列に入れない）。
- モーダルの中身は **`key={sessionKey}` で開くたびに作り直す**（前回入力の持ち越し防止。初期化の effect は書かない）。

## サーバーとのやりとり（Inertia）

- **URL は Wayfinder で組み立てる**：フォームの送信先は Controller の生成関数（`PurchaseController.update.url(id)`）、画面へのリンクは名前付きルート（`import { ledger } from '@/routes'` → `ledger.url()`）。文字列で `/purchases/...` を書かない。
- **単純なフォームは Inertia の `<Form>`**（公式キットと同じ書き方）。入力値を JS で持たず、入力欄の `name` で送る。
    - `<Form {...AuthController.login.form()}>` に Wayfinder の `.form()` を渡す。中身は関数で書き、`processing` / `errors` を受け取る。
    - `preventDefault` と `post` は `<Form>` が行うので `onSubmit` は書かない。送信後に閉じる等は `onSuccess`、消したい欄は `resetOnSuccess` / `resetOnError`。
    - 送らない画面だけの状態（規約同意のチェック等）は `useState` のまま。画面の外から届いた値（URL のクエリ等）を一緒に送るときは `<input type="hidden">` で送る。
    - **チェックボックスは `value="1"` を付ける**（付けないと `"on"` が送られ、真偽値の検証に落ちる）。外したときは送られないので、ルールは `['nullable', 'boolean']` にする。
    - 対象：ログイン・新規登録。
- **値を加工する・一部だけ空にするフォームは `useForm`**（仕入・クイック追加・売却・設定）。項目名はサーバーと同じ snake_case で書く（変換しない）。数値欄は入力中は string で持ち、`form.transform()` で送信前に数値へ直す。
- **写真などファイルを含む送信**は自動で multipart になる。PUT は `form.post(url)` + `_method: 'put'`（Laravel のメソッド偽装）。
- **一覧の絞り込み**は URL のクエリで持つ：`router.get(url, query, { preserveState: true, only: [...], reset: ['purchaseList'] })`。検索は 300ms デバウンス。
- **「もっと見る」**は部分リロード：`router.reload({ data: { currentPage }, only: ['purchaseList', 'totalCount'], preserveUrl: true })`（サーバーの `Inertia::merge()` が後ろに継ぎ足す）。
- **削除・売却などは楽観的更新**（`optimistic`）で先に画面へ反映する。失敗したら Inertia が自動で戻す（`lib/inventory-optimistic.ts`）。
- ページごとの移動（セッション切れでログイン画面を開き直す等）はサーバーの `Inertia::location()` に任せる。

## 結果の伝え方（トースト・モーダル）

- 操作結果のトーストは**サーバーのフラッシュ**（`Inertia::flash(FlashConsts::TOAST_DATA, Toast::success(...))`。キーは `toastData`）で出す。レイアウトの `useFlashToast()` が受ける。
    - 受け取りは **Inertia の flash イベント**（`router.on('flash')`。公式キットと同じ）。初回表示のフラッシュだけ `usePage().flash` からも拾う。
    - 表示は state 経由にして、同じ操作の `onSuccess`（モーダルを閉じる等）が画面に反映されてから出す（位置がモーダルの開閉で変わるため）。
    - 種類で見た目が変わる：**完了＝墨色の帯 / 失敗＝赤の帯**（`lib/toast.tsx` の `showToast(message, type)`）。画面側から出すときの既定は `success`。
    - 例外：同じ登録処理を複数の部品が使い、部品ごとに文言が違うもの（仕入の連続登録「登録しました。続けて入力できます。」／クイック追加「追加しました。」）は `onSuccess` で画面側が出す。
- 検証エラーは `form.errors.xxx`（サーバーと同じ snake_case）を `InputError`（`components/common/input-error.tsx`。公式と同じ名前・同じ props）で項目の下に出す。
- 今いる画面の判定（ナビの強調など）は `useCurrentUrl()`（`hooks/use-current-url.ts`。公式と同じ使い方）の `isCurrentUrl(href)`（完全一致）/ `isCurrentOrParentUrl(href)`（配下も含む）を使う。`usePage().url` を自前で切り分けない。
    - `form.errors.request` は**操作そのものの失敗**（権限なし・対象なし・サーバーエラー。文言はトーストで出る）。「入力内容を確認してください」等のまとめ表示には含めない（`lib/form-errors.ts` の `getInputErrorList()` を使う）。
    - 失敗すると `onSuccess` は呼ばれない。成功した時だけの処理（閉じる・入力を空にする・完了トースト）は `onSuccess` に書けばよい。
- トーストの見た目は `lib/toast.tsx`（sonner の custom。墨色の帯・タップで閉じる・4秒）。画面下の中央（下から 80px・PC は幅 384px・スマホは左右 16px 空け）。
    - **モーダルを開いている間は画面の上に出す**（下だとモーダルの保存ボタンに重なり、sonner はマウスが乗っている間は閉じないため）。トーストをタップしてもモーダルは閉じない（`BaseModal`）。

## Lint・整形・型チェック

- `npm run check`（整形 + Lint + 型を含む Vite+ のチェック）/ `npm run check:fix`（自動修正）/ `npm run types:check`（`tsc --noEmit`）。
- 整形は TS/TSX のみ（2 スペース・セミコロンなし・シングルクォート・100 桁）。Markdown・JSON・YAML・CSS は `.editorconfig` のまま。
