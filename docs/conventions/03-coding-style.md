# コーディングスタイル

## 必須ルール

### 変数
・**変数の寿命はできる限り短くする。** 変数は「最初に使う場所の直前」で定義し、使う範囲（最も狭いスコープ）にとどめる。
    - 使う場所と離れた場所で先行定義しない（読み手が「どこで使うのか」を追わなくて済む）
    - 関数の冒頭にすべての変数を並べてから処理するスタイルは取らない
    - 同じスコープで使い回さない。 ループの中だけで使う変数は、ループの中で定義する
    - **次のルール（分岐内で定義した変数を分岐外で使う場合の先行宣言）と矛盾する場合は、「分岐外でも使うかどうか」を先に判断し、分岐外で使わないなら寿命を短く保つ。**
    - NG例（関数の頭でまとめて宣言してしまい、寿命が無駄に長い）
        ```php
        $expectedPointHistoryList = [];
        // ... 数十行の前処理 ...
        foreach ($userList as $user) {
            // ループの中だけで使うのに、外で宣言されている
            foreach ($user->records as $record) {
                $expectedPointHistoryList[] = $record;
            }
            foreach ($expectedPointHistoryList as $expected) {
                $this->assertDatabaseHas('t_xxx', $expected);
            }
        }
        ```
    - OK例（使うループの直前で宣言。 ループ毎にリセットされ、寿命が最短）
        ```php
        // ... 数十行の前処理 ...
        foreach ($userList as $user) {
            $expectedPointHistoryList = [];
            foreach ($user->records as $record) {
                $expectedPointHistoryList[] = $record;
            }
            foreach ($expectedPointHistoryList as $expected) {
                $this->assertDatabaseHas('t_xxx', $expected);
            }
        }
        ```

・**`if` 文・`switch` 文など分岐の中で初めて定義する変数を、その分岐の外でも使う場合は、分岐に入る前（直前）で先に宣言しておく。**
    - 通らなかった枝のせいで「Undefined variable ...」になるのを防ぐため
    - 分岐後に必ず使う変数は、外側で 1 回必ず初期化する（配列なら `[]`、その他は `null` 等）
    - 通常の値を持てる変数（例: 設定値・日付）は、分岐前に「デフォルト値」で確定できるならその値で初期化してしまってもよい
    - 同じ変数を分岐の中で再代入する場合、分岐内側の重複した最初の定義は削除する（外で確定 → 内で必要なら上書き）
    - NG例（'b' でも 'c' でもないケースで `$result` が未定義）
        ```php
        if ($mode === 'b') {
            $result = doB();
        } else if ($mode === 'c') {
            $result = doC();
        } else {
            // no action.
        }

        // ↓ $mode が 'b' でも 'c' でもないと、 ここで「Undefined variable $result」
        return $result;
        ```
    - OK例（外で先に宣言）
        ```php
        $result = null;
        if ($mode === 'b') {
            $result = doB();
        } else if ($mode === 'c') {
            $result = doC();
        } else {
            // no action.
        }

        // ↓ どのパスを通っても $result は確実に存在する
        return $result;
        ```
    - OK例（分岐前にデフォルト値を入れて、分岐内では必要に応じて上書き）
        ```php
        $limitDate = Carbon::now()->addYears(1)->format("Y-m-t");
        if ($userData['user_id'] === '3') {
            $limitDate = Carbon::now()->subMonth()->format("Y-m-t");
        } else {
            // no action.
        }
        ```

### 分岐とログ
・**`if` 文・`switch` 文など、すべての分岐で「どの枝に入ったか」が分かるログを残す。**
    - 本番ログを見て処理経路がたどれることを優先する
    - 何のチェックをしているか、結果が OK / NG どちらだったかを明記する
    - **原則、`if` の直前に「これから何を判定/実行するか」の意図ログ（"〜します。"）を置き、各分岐（`else` 含む）に結果ログ（"〜しました。" / "〜できませんでした。"）を置く。** 業務ロジック・フロー制御の分岐（Service / Controller）には必ず適用する。
        ```php
        Log::info("〜を確認します。");            // if 直前：意図
        if ($xxx !== null) {
            Log::info("〜が見つかりました。");      // 分岐：結果（OK）
            // ...
        } else {
            Log::info("〜が見つかりませんでした。");  // 分岐：結果（NG）
            // ...
        }
        ```
    - 単純な早期リターン (e.g. `if ($x === null) return;`) や、Repository の `if (count > 0) ... else // no action`（単純なデータ有無判定）はログ省略可。業務ロジックの分岐は基本残す
    - NG例（分岐に入ったか分からない）
        ```php
        if ($loginType === '1') {
            if (isset($_SESSION['LOGIN_CTRL']) === true && $_SESSION['LOGIN_CTRL'] === 1) {
                $authorized = true;
            } else {
                $redirectUrl = '/ctrl/login';
            }
        } else {
            if (isset($_SESSION['LOGIN']) === true && $_SESSION['LOGIN'] === 1) {
                $authorized = true;
            } else {
                $redirectUrl = '/login';
            }
        }
        ```
    - OK例（経路と結果が分かる）
        ```php
        if ($loginType === '1') {
            Log::info("ctrl 画面の認証チェックを実行します。");
            if (isset($_SESSION['LOGIN_CTRL']) === true && $_SESSION['LOGIN_CTRL'] === 1) {
                Log::info("ctrl ログイン状態: OK");
                $authorized = true;
            } else {
                Log::info("ctrl ログイン状態: NG");
                $redirectUrl = '/ctrl/login';
            }
        } else {
            Log::info("通常画面の認証チェックを実行します。");
            if (isset($_SESSION['LOGIN']) === true && $_SESSION['LOGIN'] === 1) {
                Log::info("通常画面 ログイン状態: OK");
                $authorized = true;
            } else {
                Log::info("通常画面 ログイン状態: NG");
                $redirectUrl = '/login';
            }
        }
        ```

・**ログの文言は二重引用符で書く**（変数を `{$id}` のように埋め込めるため）。配列は `print_r($x, true)` で文字列にし、つなぐ `.` の前後に空白を1つ入れる。
    - OK例
        ```php
        Log::info("purchase_id: {$id}");
        Log::info("filterData: " . print_r($filterData, true));
        ```

・**分岐の中で「Log::info で枝の説明」と「本処理」が続く場合、Log の後に 空行 + `//` の区切りを入れる。** `switch-case` の `case` 直下も同じ。
    - 「枝の説明（ログ）」と「実際の処理（コード）」を視覚的に分離する
    - Log の直後にコードが続くと、どこからが本処理かが一目で分かりにくい
    - 既存の「`$result = [...]; return $result;` の直前に空行 + `//`」と同じ思想（境界に区切りを入れる）
    - 例外: 分岐の中身が `Log::info` だけ・`break;` だけ・`return $x;` だけ等、本処理が無いケースは区切り不要
    - NG例（Log の直後にいきなり処理が続く）
        ```php
        if ($postId !== '0') {
            $postData = $postDataByPostId[$postId] ?? null;
            if ($postData !== null) {
                Log::info("post_id ({$postId}) の t_post を見つけました。");
                $postConfirmData['title'] = $postData['title'] ?? '';
                $content = $postData['content'] ?? '';
            } else {
                Log::info("post_id ({$postId}) に対応する t_post が見つかりませんでした。");
                $content = '';
            }
        } else {
            Log::info("post_id == 0 のためユーザー個別のお知らせとして処理します。");
            $content = $postConfirmData['content'] ?? '';
        }
        ```
    - OK例（Log の後ろ・`{` の後ろに 空行 + `//` を入れる）
        ```php
        if ($postId !== '0') {

            //
            $postData = $postDataByPostId[$postId] ?? null;
            if ($postData !== null) {
                Log::info("post_id ({$postId}) の t_post を見つけました。");

                //
                $postConfirmData['title'] = $postData['title'] ?? '';
                $content = $postData['content'] ?? '';
            } else {
                Log::info("post_id ({$postId}) に対応する t_post が見つかりませんでした。");

                //
                $content = '';
            }
        } else {
            Log::info("post_id == 0 のためユーザー個別のお知らせとして処理します。");

            //
            $content = $postConfirmData['content'] ?? '';
        }
        ```
    - OK例（switch-case でも同じ）
        ```php
        switch ($subRoute) {
            case 'units':
                Log::info("tools02 サブルート: units");

                //
                $list[2] = ['path' => "{$path1}/units", 'name' => 'ユニット 一覧'];
                break;
            case 'unit-detail':
                Log::info("tools02 サブルート: unit-detail");

                //
                $list[2] = ['path' => "{$path1}/units", 'name' => 'ユニット 一覧'];
                $list[3] = ['path' => "{$path1}/unit-detail/{$id}", 'name' => 'ユニット 詳細'];
                break;
        }
        ```

### if文
・「!」は分かりにくいので使わない。
  - NG: if (!$isBatchProcessingAsp) {}
  - OK: if ($isBatchProcessingAsp === false) {}

・**if 文の条件式内でキャスト ((string) / (int) / (bool) 等) や非自明な加工を行わない。 if の直前で変数に格納してから比較する。**
    - 条件式が短く読みやすくなる
    - 同じ加工値を if / else や if 内のさらに内側で複数回使うときに、毎回キャストせずに済む（DRY）
    - 変数名で値の意味・型が明示される
    - 加工した値そのものをログに出したい場面でもそのまま使える
    - NG例（if の中・配列添字の中で毎回キャストしている）
        ```php
        if ((string)$postConfirmData['post_id'] !== '0') {
            $postData = $postDataByPostId[(string)$postConfirmData['post_id']] ?? null;
            if ($postData !== null) {
                Log::info("post_id ({$postConfirmData['post_id']}) の t_post を見つけました。");
                // ...
            } else {
                Log::info("post_id ({$postConfirmData['post_id']}) に対応する t_post が見つかりませんでした。");
                // ...
            }
        }
        ```
    - OK例（直前で `$postId = (string)...` として確定）
        ```php
        $postId = (string)$postConfirmData['post_id'];
        if ($postId !== '0') {
            $postData = $postDataByPostId[$postId] ?? null;
            if ($postData !== null) {
                Log::info("post_id ({$postId}) の t_post を見つけました。");
                // ...
            } else {
                Log::info("post_id ({$postId}) に対応する t_post が見つかりませんでした。");
                // ...
            }
        }
        ```

・**条件式の結果を真偽値変数にワンライナーで代入しない。 if 文で分岐して値を設定する。**
    - ワンライナー (`$isXxx = ($a === 'b')`) は意図が読み取りにくく、どの枝に入ったかのログを差し込めない
    - デフォルト値で先に宣言 → if で必要なら上書き、というパターンを基本とする（→ [変数](#変数) の「分岐外でも使う変数は先行宣言」と整合）
    - 各分岐には [分岐とログ](#分岐とログ) のルールどおりログを残す
    - NG例（条件式を直接変数に代入、ログも残らない）
        ```php
        $isAllMode = ($mode === 'all');
        $unconfirmedOnly = ($isAllMode === false);
        $list = $repo->getList($userId, $unconfirmedOnly);
        ```
    - OK例（if 文で分岐 + ログ）
        ```php
        $unconfirmedOnly = true;
        if ($mode === 'all') {
            Log::info("mode=all のため全件取得します。");
            $unconfirmedOnly = false;
        } else {
            Log::info("mode 未指定のため未確認のみを取得します。");
        }
        $list = $repo->getList($userId, $unconfirmedOnly);
        ```

・**配列の値に条件式（比較・三項演算子）を書かない。 先に if で変数を決めてから、その変数を渡す。**（上の「真偽値変数にワンライナーで代入しない」と同じ考え方） **🤖 `composer phpstan` で確かめる**（自作ルール `tests/PHPStan/Rules/NoConditionInArrayValueRule.php`。キー付きの要素の値が比較・三項演算子なら指摘）
    - NG例：`'is_sold' => $saleData !== null,` ／ `'price' => $price === null ? null : (int)$price,`
    - OK例：
        ```php
        $price = null;
        if ($purchaseData['price'] !== null) {
            $price = (int)$purchaseData['price'];
        } else {
            // no action.
        }

        //
        $result = [
            'price' => $price,
        ];
        ```
    - 任意（nullable）項目で型をそろえる必要がないもの（検証済みの日付の文字列など）は、`validated()` をそのまま入れてよい（07-architecture）

・**関数呼び出しの引数の中でインライン代入 (`func($arg = expr)`) を行わない。 呼び出し前に変数を確定させてから渡す。**
    - インライン代入は変数の生まれた場所が引数の中に埋もれ、読み手が見落としやすい
    - インライン代入は単なる「変数の宣言」と区別がつかず、本当に代入したいのか引数の名前付き渡しのつもりなのかが曖昧になる（PHP 8 の名前付き引数は `func(name: $value)` と書くので、`=` の書き方はただの代入になり、誤解しか生まない）
    - NG例（引数の中で `$unconfirmedOnly` を初出させている）
        ```php
        $postConfirmList = $this->postConfirmRepository->getReleasedListByUserId($userId, $unconfirmedOnly = ($isAllMode === false));
        ```
    - OK例（呼び出し前に変数を確定）
        ```php
        $unconfirmedOnly = ($isAllMode === false);
        $postConfirmList = $this->postConfirmRepository->getReleasedListByUserId($userId, $unconfirmedOnly);
        ```

### if-else-if-else 文
・基本的に分岐の全てにおいて、分かりやすいログを残す（→ [分岐とログ](#分岐とログ) 参照）
・if() の中身は基本的に肯定文 & 基本的にelseでエラーを補足する & 同じ内容を判定する（例えば、「空チェック」と「正規表現チェック」をif-else-ifで並べない）
    ‐ NG
        ```
        if ($ydaTagId === '') {
            $validator->errors()->add('yda_tag_id', '入力してください。');
        } else if (preg_match('/\s/', $ydaTagId)) {
            $validator->errors()->add('yda_tag_id', '空白を含めることはできません。');
        } else if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $ydaTagId) === false || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $ydaTagId) === 0) {
            $validator->errors()->add('yda_tag_id', '形式が正しくありません。');
        } else {
            // ok.
        }
        ```
    - OK
        ```
        if ($ydaTagId !== '') {
            // 空白がないことを確認
            if (preg_match('/\s/', $ydaTagId) === 0) {
                // 形式が正しいことを確認
                if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $ydaTagId) === 1) {
                    // ok.
                } else {
                    $validator->errors()->add('yda_tag_id', '形式が正しくありません。');
                }
            } else {
                $validator->errors()->add('yda_tag_id', '空白を含めることはできません。');
            }
        } else {
            $validator->errors()->add('yda_tag_id', '入力してください。');
        }
        ```

・ただし、独立した複数のチェックを順番に行う場合（バリデーション等）は、ネストせずに早期リターンで書く。ネストが深くなると可読性が悪くなるため。
・早期リターンの場合も、if() の中身は肯定文にし、else側でreturn falseする。
    - NG（不要なネスト）
        ```
        if (条件A) {
            if (条件B) {
                if (条件C) {
                    // ok.
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
        ```
    - NG（if の中身が否定文）
        ```
        if (条件Aを満たさない) {
            return false;
        }

        if (条件Bを満たさない) {
            return false;
        }

        // ok.
        return true;
        ```
    - OK（早期リターン + 肯定文 + else でエラー）
        ```
        if (条件Aを満たす) {
            // ok.
        } else {
            return false;
        }

        if (条件Bを満たす) {
            // ok.
        } else {
            return false;
        }

        // ok.
        return true;
        ```

### switch-case文
・すべてのケースが意味のある順序で並んでいるか。
・各ケースの処理が単純に保たれ、必要に応じて他のメソッドを呼び出しているか。
・case文は本物の変数を評価しているか（case文のために作った仮の変数ではなく）。
・default句を正しく使っているか。
・default句を予想外のケースの検出と報告に使っているか。

### PHP固有ルール
・「empty($xxx)」は原則、使用しない。「count($xxx) > 0」もしくは「isset($xxx)」を使用する

### return文

> 注: 以下の例で配列に `'status' => 401` 等を入れているのは **return の構造**（`$result` 経由・エラー早期 return・末尾は正常）を示すための例。このリポジトリの HTTP の返し方は [10-api-conventions.md](10-api-conventions.md) のとおり（Service は素のデータ配列を返し、Controller が画面の表示・リダイレクト・`abort(404)` 等に振り分ける）。`GeneralConsts::NO_SESSION_MESSAGE` 等は説明用のプレースホルダ（このリポジトリには無い）。

・**`return` 文の直前には原則 `//` の区切り行（空行 + `//`）を置く**（直前の処理と「返却」を視覚的に分離するため）。**ただし `$result = [...]`（配列リテラル）の組み立ての直後に `return $result;` を書く場合は、組み立ての前にのみ `//` を置き、`return` は組み立てに続けて書く**（組み立てと返却で 1 つの単位＝「返す形」が一目で分かる。間に区切りを入れない）。Controller の `// return` セクションも同じ役割。
    - NG例（組み立てと return の間に区切りを入れている）
        ```php
        //
        $result = [ /* ... */ ];

        //
        return $result;
        ```
    - OK例（組み立ての前にのみ `//`、`return` は続けて書く）
        ```php
        //
        $result = [ /* ... */ ];
        return $result;
        ```
・**Controller の `// return` セクションが if（OK / NG の振り分け）から始まる場合、if に続く最後の `return` にも区切り（空行 + `//`）を入れない**（if と最後の `return` までを「返却」の1つのまとまりとして見せるため。上の `$result` の組み立てと同じ考え方）。
    - OK例
        ```php
        // return
        if ($result['success'] === true) {
            // ok.
        } else {
            Log::info("ログイン失敗のためログイン画面へ戻します。");

            //
            return back()->withErrors([/* ... */]);
        }
        return redirect()->intended(route('home'));
        ```

・**配列（と `match`）の `=>` の前は空白1つ。縦ぞろえはしない**（公式スターターキット・Laravel 本体と同じ。Pint の標準設定もこの形）。
    - 縦にそろえると、長いキーを1つ足しただけで同じ配列のほかの行もすべて空白を直すことになり、git の差分に関係のない行が出る。Pint を使っていないので、ずれても自動では直らない
    - NG例（縦ぞろえ）
        ```php
        $result = [
            'sale_price'           => (int)$request->validated('sale_price'),
            'counterparty_address' => $request->validated('counterparty_address'),
        ];
        ```
    - OK例
        ```php
        $result = [
            'sale_price' => (int)$request->validated('sale_price'),
            'counterparty_address' => $request->validated('counterparty_address'),
        ];
        ```

・**アロー関数は `fn() =>`（`fn` と `(` のあいだに空白を入れない）**。引数があっても同じ（`fn(Assert $page) => ...`、`fn(): string => ...`）。 **🤖 `composer phpcs` で確かめる**（自作ルール `phpcs/Kobucho/Sniffs/Functions/ArrowFunctionSpacingSniff.php`）
    - プロジェクト内で書き方を1つにそろえるため。Pint を使っていないので、ずれても自動では直らない
    - NG例：`'totalCount' => fn () => $getListResult()['total_count'],`
    - OK例：`'totalCount' => fn() => $getListResult()['total_count'],`

・**キャストのあとに空白を入れない**（`(string)$x`）。 **🤖 `composer phpcs` で確かめる**（`phpcs.xml` の `Generic.Formatting.SpaceAfterCast`）
    - NG例：`$userId = (string) $request->user()->id;`
    - OK例：`$userId = (string)$request->user()->id;`

・**配列リテラルを直接 `return` しない。一旦 `$result` 等の変数に代入してから return する。**
    - デバッガでブレーク・ダンプしやすくする / ログを差し込みやすくするため
    - 配列以外のスカラー値はそのまま return して良い
    - NG例
        ```php
        return [
            'status' => 401,
            'message' => GeneralConsts::NO_SESSION_MESSAGE,
            'redirect_url' => $redirectUrl,
        ];
        ```
    - OK例
        ```php
        $result = [
            'status' => 401,
            'message' => GeneralConsts::NO_SESSION_MESSAGE,
            'redirect_url' => $redirectUrl,
        ];
        return $result;
        ```

・**関数の構造は「エラーは早期リターン、最後まで生き残ったら正常を返す」を基本にする。**
    - エラー側を if/else の両枝で並列に return しない（どちらが正常か読み取りにくくなる）
    - エラーで分岐したら早めに return して、関数末尾は常に「正常終了」に固定する
    - これにより末尾を見ただけで「この関数の正常結果」が分かる
    - NG例（if/else の両枝で return している → 正常パスが埋もれる）
        ```php
        if ($authorized === true) {
            Log::info("認証 OK のため 200 を返します。");
            $result = [
                'status' => 200,
            ];
            return $result;
        } else {
            Log::info("認証 NG のため 401 を返します。 redirectUrl: {$redirectUrl}");
            $result = [
                'status' => 401,
                'message' => GeneralConsts::NO_SESSION_MESSAGE,
                'redirect_url' => $redirectUrl,
            ];
            return $result;
        }
        ```
    - OK例（エラー側で早期 return、正常側は関数末尾まで生き残る）
        ```php
        if ($authorized === true) {
            // ok.
        } else {
            Log::info("認証 NG のため 401 を返します。 redirectUrl: {$redirectUrl}");

            //
            $result = [
                'status' => 401,
                'message' => GeneralConsts::NO_SESSION_MESSAGE,
                'redirect_url' => $redirectUrl,
            ];
            return $result;
        }

        //
        Log::info("認証 OK のため 200 を返します。");

        //
        $result = [
            'status' => 200,
        ];
        return $result;
        ```

・**`$result = [...]; return $result;` のセットの直前には空行 + `//` の区切りを入れる**(直前の処理 (Log::info など) と「return 用の値組み立て」を視覚的に分離)
    - 「ここから return の組み立てが始まる」が一目で分かる
    - 同じく `//` で始まるセクションコメント (`// log` / `// execute` / `// return` 等) と統一感が出る
    - NG例（直前の処理から地続きで `$result = [...]` が始まる）
        ```php
        Log::info("認証 NG のため 401 を返します。 redirectUrl: {$redirectUrl}");
        $result = [
            'status' => 401,
            'message' => GeneralConsts::NO_SESSION_MESSAGE,
            'redirect_url' => $redirectUrl,
        ];
        return $result;
        ```
    - OK例（空行 + `//` を挟む）
        ```php
        Log::info("認証 NG のため 401 を返します。 redirectUrl: {$redirectUrl}");

        //
        $result = [
            'status' => 401,
            'message' => GeneralConsts::NO_SESSION_MESSAGE,
            'redirect_url' => $redirectUrl,
        ];
        return $result;
        ```

・**「結果が空のときは早期 return」のような正常パス内のトリビアルな分岐は早期 return せず、変数をデフォルト値（空配列・null 等）で初期化し、条件を満たす場合のみ取得・上書きしてから関数末尾で 1 回だけ return する。**
    - エラー時の早期 return（前ルール）とは別。これは「正常範囲の中で、入力が空ならそのまま空を返す」ような単純ケースを扱うときの形
    - return を関数末尾の 1 箇所にまとめることで、「この関数の結果は何か」を末尾だけ見て把握できる
    - 「if のために return が増える」のを避けるため、`$list = []` のような先行宣言＋条件付き上書き形にする
    - NG例（取得結果が空になるケースを早期 return している → return が 2 箇所に分散）
        ```php
        public function getListByPostIdList(array $postIdList): array
        {
            //
            if (count($postIdList) === 0) {
                return [];
            } else {
                // no action.
            }

            //
            $list = $this->post
                ->whereIn('post_id', $postIdList)
                ->where('enable', true)
                ->get()
                ->toArray();

            //
            return $list;
        }
        ```
    - OK例（空配列で初期化 → 条件を満たすときだけ上書き → 末尾で 1 回だけ return）
        ```php
        public function getListByPostIdList(array $postIdList): array
        {
            //
            $list = [];
            if (count($postIdList) > 0) {
                $list = $this->post
                    ->whereIn('post_id', $postIdList)
                    ->where('enable', true)
                    ->get()
                    ->toArray();
            } else {
                // no action.
            }

            //
            return $list;
        }
        ```

### メソッド定義
・`public function` には原則、PHPDocコメント（`@param` / `@return` 付き）を付与する。
    - 説明文は必ず何かしら記載する（「Undocumented function」等のプレースホルダはNG）。メソッドの役割・目的を1行で日本語で記載する。
    - OK例
        ```php
        /**
         * コンバージョン時間を更新する
         *
         * @param UpdateConversionTimeRequest $request
         * @return array
         */
        public function updateConversionTime(UpdateConversionTimeRequest $request): array
        {
            // ...
        }
        ```
    - NG例
        ```php
        /**
         * Undocumented function
         *
         * @param UpdateConversionTimeRequest $request
         * @return array
         */
        public function updateConversionTime(UpdateConversionTimeRequest $request): array
        {
            // ...
        }
        ```

・`public function` 同士の間は **空行3行** あける（PHPDocコメントを含むメソッドブロック間の空行）。 **🤖 `composer phpcs` で確かめる**（`app/` のみ。クラスの `{` の直後と最後の `}` の前は0行。インターフェース（Contracts）・テストは対象外）
    - OK例
        ```php
            public function resendOfflineConversions(ResendOfflineConversionsRequest $request): array
            {
                // ...
            }



            /**
             * Undocumented function
             *
             * @param UpdateConversionTimeRequest $request
             * @return array
             */
            public function updateConversionTime(UpdateConversionTimeRequest $request): array
            {
                // ...
            }
        ```

・**例外: data_provider と、それを `#[DataProvider]` で使うテストメソッドの間は空行を空けない**（ペアを1つの単位として視認しやすくするため）。
    - 同じ data_provider と consumer test method がペアであることを示す
    - 他の `public function` 同士は通常通り 3行あける
    - テスト属性は `#[Test]` / `#[DataProvider(...)]`、provider は `public static`（[08-testing-conventions.md](08-testing-conventions.md) 参照）
    - `tests/Feature/` では、テストメソッドの直後に data_provider を続けて書いている
    - NG例
        ```php
            #[Test]
            #[DataProvider('data_provider_for_updateNameTest')]
            public function updateNameTest(string $testCase, array $payload, bool $expectsSuccess): void
            {
                // ...
            }



            public static function data_provider_for_updateNameTest(): array
            {
                return [ /* ... */ ];
            }
        ```
    - OK例
        ```php
            #[Test]
            #[DataProvider('data_provider_for_updateNameTest')]
            public function updateNameTest(string $testCase, array $payload, bool $expectsSuccess): void
            {
                // ...
            }
            public static function data_provider_for_updateNameTest(): array
            {
                return [ /* ... */ ];
            }
        ```

・FormRequest の `rules()` は **配列形式** で書く。`|` 区切りの文字列形式は使わない。
    - 各ルールが独立した要素になるので、追加削除・条件分岐が読みやすい
    - `Rule::in(...)` などのオブジェクト型ルールを混ぜる場合にも統一できる
    - NG例
        ```php
        return [
            'name' => 'required|string',
            'email' => 'required|email',
            'age' => 'nullable|integer|min:0',
        ];
        ```
    - OK例
        ```php
        return [
            'name' => ['required', 'string'],
            'email' => ['required', 'email'],
            'age' => ['nullable', 'integer', 'min:0'],
        ];
        ```

・**FormRequest に `authorize()` は定義しない。**
    - Laravel は `authorize()` が未定義の場合、認可を常に通す (`passesAuthorization()` が `true` を返す) ため、`return true;` だけの `authorize()` は冗長
    - 認証はルートのミドルウェア（`auth` / `guest`）、本人のデータかどうかの確認は Service・Repository（`user_id` で絞る）や検証ルール（`Rule::exists(...)->where('user_id', ...)`）で行う方針なので、FormRequest 側に `authorize()` は置かない
    - `false` を返したい (= リクエスト自体を拒否したい) ような特殊ケースが出たときだけ、理由をコメントで明示して定義する
    - NG例（`return true;` だけの冗長な `authorize()`）
        ```php
        class GetDataRequest extends FormRequest
        {
            public function authorize()
            {
                return true;
            }

            public function rules()
            {
                // ...
            }
        }
        ```
    - OK例（`authorize()` を書かない）
        ```php
        class GetDataRequest extends FormRequest
        {
            public function rules()
            {
                // ...
            }
        }
        ```

・**FormRequest は Laravel 標準のまま（`rules()` と必要なら `messages()` だけ）。 `failedValidation()` は上書きしない。**
    - `rules()` 冒頭にログを入れる（どのリクエストの検証が始まったか）
        - 「validation start.」 → 検証の開始
        - `parameterData` ダンプ → 受け取った入力（**パスワード等は `except()` で外す**）
    - 検証失敗の記録は `App\Http\Middleware\LogValidationError`（ミドルウェア）が1か所で行う。各 FormRequest に同じコードを置かない
    - `rules()` も `public function` なので、他のメソッドと同様に PHPDoc (説明文 + `@param` / `@return`) を付ける (→ [メソッド定義](#メソッド定義) 参照)
・**`in:` / `not_in:` などの選択肢ルールは、文字列ではなく `Rule::in(...)` / `Rule::notIn(...)` (オブジェクト型) を使う。**
    - 値に `,` が含まれる場合のエスケープ問題を回避できる
    - 配列で渡せるので、定数 (`*Consts::*_LIST` 等) をそのまま渡せて IDE 補完が効く
    - PHPStan などの静的解析と相性が良い
    - NG例
        ```php
        return [
            'status' => ['nullable', 'in:in_stock,sold,draft,all'],
            'channel' => ['required', 'in:1,2,3,4,5,6,9'],
        ];
        ```
    - OK例
        ```php
        use App\Enums\SalesChannel;
        use Illuminate\Validation\Rule;

        return [
            'status' => ['nullable', Rule::in(['in_stock', 'sold', 'draft', 'all'])],
            // 固定種別を Enum で管理しているフィールドは Rule::enum でそのまま検証できる
            'channel' => ['required', Rule::enum(SalesChannel::class)],
        ];
        ```

・クラス内のメソッドは **public を上、private を下** にまとめる。
    - 順序: プロパティ宣言 → コンストラクタ → public メソッド群 → private メソッド群
    - 公開 API（外部から呼ばれるもの）が一目で分かるようにするため
    - public 同士・private 同士は呼び出し順や関連順で並べてよい
    - public と private を交互に並べない
    - NG例
        ```
        class FooService
        {
            public function __construct(...) {}

            public function publicA(): array { /* ... */ }

            private function helperForA(): string { /* ... */ }   // ← public の合間に private

            public function publicB(): array { /* ... */ }

            private function helperForB(): string { /* ... */ }
        }
        ```
    - OK例
        ```
        class FooService
        {
            public function __construct(...) {}

            public function publicA(): array { /* ... */ }

            public function publicB(): array { /* ... */ }

            private function helperForA(): string { /* ... */ }

            private function helperForB(): string { /* ... */ }
        }
        ```

---

実装時のレビュー観点チェックリストは [90-review-checklist.md](90-review-checklist.md) を参照。
