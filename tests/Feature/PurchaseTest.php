<?php

namespace Tests\Feature;

use App\Consts\ToastConsts;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;

    public function setUp(): void
    {
        parent::setUp();

        // ----- テストユーザー（本人 + 別ユーザー、Factory）-----
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    }



    /**
     * 確定済みの正しい仕入ペイロード（画面と同じ camelCase。テストで一部を上書きして使う）
     *
     * @param array $override
     * @return array
     */
    private function confirmedPayload(array $override = []): array
    {
        return array_merge([
            'transaction_date' => '2026-07-01',
            'kubun' => '1',
            'item_name' => 'ジャンクカメラ',
            'quantity' => 2,
            'features' => 'Nikon F3・並品',
            'counterparty_name' => 'ハードオフ西宮店',
            'counterparty_address' => '兵庫県西宮市',
            'verification_method' => '4',
            'price' => 3000,
            'is_exempt' => false,
            'is_draft' => false,
        ], $override);
    }



    // ============================ register（登録・連続登録） ============================

    #[Test]
    #[DataProvider('data_provider_for_registerValidationTest')]
    public function registerValidationTest(string $testCase, array $payload, string $expected): void
    {
        // 認証状態を用意（未認証ケースのみ actingAs しない）
        if ($testCase === 'unauthenticated') {
            // no action.
        } else {
            $this->actingAs($this->user);
        }

        //
        $response = $this->from(route('home'))->post(route('purchases.register'), $payload);

        //
        if ($expected === 'created') {
            // 元の画面（在庫）へ戻す。完了の案内は画面側で出す
            $response->assertRedirect(route('home'));
            $response->assertSessionHasNoErrors();
            $this->assertDatabaseCount('purchases', 1);
        } elseif ($expected === 'unauthenticated') {
            $response->assertRedirect(route('login'));
            $this->assertDatabaseCount('purchases', 0);
        } else {
            // 入力エラー：該当項目のエラーを載せて戻し、行は作らない
            $response->assertRedirect(route('home'));
            $response->assertSessionHasErrors($expected);
            $this->assertDatabaseCount('purchases', 0);
        }
    }
    public static function data_provider_for_registerValidationTest(): array
    {
        $confirmed = [
            'transaction_date' => '2026-07-01',
            'kubun' => '1',
            'item_name' => 'ジャンクカメラ',
            'quantity' => 2,
            'counterparty_name' => 'ハードオフ西宮店',
            'verification_method' => '4',
            'price' => 3000,
        ];

        return [
            'unauthenticated' => [
                'testCase' => 'unauthenticated',
                'payload' => $confirmed,
                'expected' => 'unauthenticated',
            ],
            'success_confirmed' => [
                'testCase' => 'success_confirmed',
                'payload' => $confirmed,
                'expected' => 'created',
            ],
            // 下書き＝金額のみでも通る（現場捕獲）
            'success_draft_minimal' => [
                'testCase' => 'success_draft_minimal',
                'payload' => ['transaction_date' => '2026-07-01', 'kubun' => '1', 'price' => 500, 'is_draft' => true],
                'expected' => 'created',
            ],
            // 確認免除（1万円未満）は確定でも相手方不要
            'success_exempt_no_counterparty' => [
                'testCase' => 'success_exempt_no_counterparty',
                'payload' => [
                    'transaction_date' => '2026-07-01',
                    'kubun' => '1',
                    'item_name' => '古本',
                    'verification_method' => '9',
                    'price' => 500,
                    'is_exempt' => true,
                ],
                'expected' => 'created',
            ],
            'invalid_kubun' => [
                'testCase' => 'invalid_kubun',
                'payload' => array_merge($confirmed, ['kubun' => '99']),
                'expected' => 'kubun',
            ],
            // 受入れの区別は法令上「買受け／委託」のみ。旧「交換(3)／返品(4)」は法令に無いので弾く
            'removed_kubun_exchange' => [
                'testCase' => 'removed_kubun_exchange',
                'payload' => array_merge($confirmed, ['kubun' => '3']),
                'expected' => 'kubun',
            ],
            'removed_kubun_return' => [
                'testCase' => 'removed_kubun_return',
                'payload' => array_merge($confirmed, ['kubun' => '4']),
                'expected' => 'kubun',
            ],
            'missing_transaction_date' => [
                'testCase' => 'missing_transaction_date',
                'payload' => array_diff_key($confirmed, ['transaction_date' => null]),
                'expected' => 'transaction_date',
            ],
            'confirmed_missing_price' => [
                'testCase' => 'confirmed_missing_price',
                'payload' => array_diff_key($confirmed, ['price' => null]),
                'expected' => 'price',
            ],
            'confirmed_missing_item_name' => [
                'testCase' => 'confirmed_missing_item_name',
                'payload' => array_diff_key($confirmed, ['item_name' => null]),
                'expected' => 'item_name',
            ],
            'confirmed_missing_verification' => [
                'testCase' => 'confirmed_missing_verification',
                'payload' => array_diff_key($confirmed, ['verification_method' => null]),
                'expected' => 'verification_method',
            ],
            // 確定・非免除で相手方氏名が無い（様式15号の記載義務違反）
            'confirmed_missing_counterparty' => [
                'testCase' => 'confirmed_missing_counterparty',
                'payload' => array_diff_key($confirmed, ['counterparty_name' => null]),
                'expected' => 'counterparty_name',
            ],
        ];
    }



    #[Test]
    public function registerConfirmedShapesPropsTest(): void
    {
        $this->actingAs($this->user);

        //
        $this->from(route('home'))->post(route('purchases.register'), $this->confirmedPayload())->assertRedirect(route('home'));

        // DB には int の区分コードで保存される
        $this->assertDatabaseHas('purchases', [
            'user_id' => $this->user->id,
            'item_name' => 'ジャンクカメラ',
            'kubun' => 1,
            'price' => 3000,
            'is_draft' => false,
        ]);

        // 在庫画面の props は camelCase、区分コードは string、金額・数量は int（02-naming-conventions）
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('inventory')
                ->where('totalCount', 1)
                ->has('purchaseList', 1, fn(Assert $row) => $row
                    ->whereType('id', 'string')
                    ->where('item_name', 'ジャンクカメラ')
                    ->where('kubun', '1')
                    ->where('verification_method', '4')
                    ->where('quantity', 2)
                    ->where('price', 3000)
                    ->where('is_draft', false)
                    // 未売却・下書きでないので在庫中、売却情報・粗利は無い
                    ->where('status', 'in_stock')
                    ->where('sale_data', null)
                    ->where('raw_gross_profit', null)
                    ->where('photo_list', [])
                    ->etc()));
    }



    #[Test]
    public function registerDraftKeepsStatusDraftTest(): void
    {
        $this->actingAs($this->user);

        //
        $this->from(route('home'))->post(route('purchases.register'), [
            'transaction_date' => '2026-07-01',
            'kubun' => '1',
            'price' => 1200,
            'is_draft' => true,
        ])->assertRedirect(route('home'));

        //
        $this->assertDatabaseHas('purchases', [
            'user_id' => $this->user->id,
            'is_draft' => true,
            'price' => 1200,
        ]);
        $this->get(route('home', ['status' => 'draft']))
            ->assertInertia(fn(Assert $page) => $page
                ->where('purchaseList.0.status', 'draft')
                ->where('purchaseList.0.is_draft', true)
                ->where('purchaseList.0.item_name', null));
    }



    #[Test]
    public function registerAcceptsFormDataBooleansTest(): void
    {
        $this->actingAs($this->user);

        // 写真付きの送信は multipart になり、真偽値は '1' / '0' で届く
        $this->from(route('home'))->post(route('purchases.register'), [
            'transaction_date' => '2026-07-01',
            'kubun' => '1',
            'price' => '800',
            'is_exempt' => '0',
            'is_draft' => '1',
            'item_name' => '',
        ])->assertRedirect(route('home'))->assertSessionHasNoErrors();

        //
        $this->assertDatabaseHas('purchases', [
            'user_id' => $this->user->id,
            'is_draft' => true,
            'is_exempt' => false,
            'item_name' => null,
            'price' => 800,
        ]);
    }



    // ============================ getList（在庫画面・検索・状態フィルタ） ============================

    #[Test]
    #[DataProvider('data_provider_for_getListTest')]
    public function getListTest(string $testCase, array $queryData, array $expected): void
    {
        // 認証状態を用意（未認証ケースのみ actingAs しない）
        if ($testCase === 'unauthenticated') {
            // no action.
        } else {
            $this->actingAs($this->user);

            // 自分の仕入2件 + 別ユーザーの1件（自分の分だけ返ることを確認）
            Purchase::factory()->count(2)->create(['user_id' => $this->user->id]);
            Purchase::factory()->create(['user_id' => $this->otherUser->id]);
        }

        //
        $response = $this->get(route('home', $queryData));

        //
        if ($expected['result'] === 'ok') {
            $response->assertOk()
                ->assertInertia(fn(Assert $page) => $page
                    ->component('inventory')
                    ->where('totalCount', $expected['total'])
                    ->has('purchaseList', $expected['count'])
                    ->where('filterData', $expected['filterData'])
                    ->where('pageSize', $expected['page_size'])
                    ->etc());
        } elseif ($expected['result'] === 'unauthenticated') {
            $response->assertRedirect(route('login'));
        } else {
            // 不正なクエリは、クエリを外した在庫画面へ戻す（同じ失敗を繰り返さない）
            $response->assertRedirect(route('home'));
            $response->assertSessionHasErrors($expected['errorKey']);
        }
    }
    public static function data_provider_for_getListTest(): array
    {
        return [
            'unauthenticated' => [
                'testCase' => 'unauthenticated',
                'queryData' => [],
                'expected' => ['result' => 'unauthenticated'],
            ],
            // 既定は「在庫中」・1ページ目・20件
            'own_only_default' => [
                'testCase' => 'own_only_default',
                'queryData' => [],
                'expected' => [
                    'result' => 'ok',
                    'total' => 2,
                    'count' => 2,
                    'filterData' => ['status' => 'in_stock', 'q' => ''],
                    'page_size' => 20,
                ],
            ],
            // 画面はサーバーと同じ項目名（snake_case）のクエリで送る
            'camel_case_paging' => [
                'testCase' => 'camel_case_paging',
                'queryData' => ['current_page' => 1, 'page_size' => 1, 'status' => 'all'],
                'expected' => [
                    'result' => 'ok',
                    'total' => 2,
                    'count' => 1,
                    'filterData' => ['status' => 'all', 'q' => ''],
                    'page_size' => 1,
                ],
            ],
            'invalid_status' => [
                'testCase' => 'invalid_status',
                'queryData' => ['status' => 'unknown'],
                'expected' => ['result' => 'invalid', 'errorKey' => 'status'],
            ],
            'too_large_page_size' => [
                'testCase' => 'too_large_page_size',
                'queryData' => ['page_size' => 101],
                'expected' => ['result' => 'invalid', 'errorKey' => 'page_size'],
            ],
        ];
    }



    #[Test]
    public function getListStatusFilterTest(): void
    {
        $this->actingAs($this->user);

        // 在庫中1・下書き1・売却済1 を用意する
        Purchase::factory()->create(['user_id' => $this->user->id, 'item_name' => '在庫の品']);
        Purchase::factory()->draft()->create(['user_id' => $this->user->id]);
        $sold = Purchase::factory()->create(['user_id' => $this->user->id]);
        Sale::create([
            'user_id' => $this->user->id,
            'purchase_id' => $sold->id,
            'sale_date' => '2026-07-10',
            'channel' => '1',
            'sale_price' => 8000,
        ]);

        // in_stock は1件、draft は1件、sold は1件、all は3件
        $this->get(route('home', ['status' => 'in_stock']))->assertInertia(fn(Assert $page) => $page
            ->where('totalCount', 1)->where('purchaseList.0.status', 'in_stock'));
        $this->get(route('home', ['status' => 'draft']))->assertInertia(fn(Assert $page) => $page
            ->where('totalCount', 1)->where('purchaseList.0.status', 'draft'));
        $this->get(route('home', ['status' => 'sold']))->assertInertia(fn(Assert $page) => $page
            ->where('totalCount', 1)->where('purchaseList.0.status', 'sold'));
        $this->get(route('home', ['status' => 'all']))->assertInertia(fn(Assert $page) => $page
            ->where('totalCount', 3)->has('purchaseList', 3));
    }



    #[Test]
    public function getListSearchTest(): void
    {
        $this->actingAs($this->user);

        Purchase::factory()->create(['user_id' => $this->user->id, 'item_name' => 'ライカのカメラ']);
        Purchase::factory()->create(['user_id' => $this->user->id, 'item_name' => '古い本', 'features' => '状態良好']);

        // 品目の部分一致で1件だけヒットする
        $this->get(route('home', ['q' => 'カメラ']))
            ->assertInertia(fn(Assert $page) => $page
                ->where('filterData.q', 'カメラ')
                ->where('totalCount', 1)
                ->where('purchaseList.0.item_name', 'ライカのカメラ'));
    }



    #[Test]
    public function getListRawGrossProfitTest(): void
    {
        $this->actingAs($this->user);

        // 仕入3000・売却8000 → 素朴粗利5000
        $purchase = Purchase::factory()->create(['user_id' => $this->user->id, 'price' => 3000]);
        Sale::create([
            'user_id' => $this->user->id,
            'purchase_id' => $purchase->id,
            'sale_date' => '2026-07-10',
            'channel' => '1',
            'sale_price' => 8000,
        ]);

        $this->get(route('home', ['status' => 'sold']))
            ->assertInertia(fn(Assert $page) => $page
                ->where('purchaseList.0.raw_gross_profit', 5000)
                ->where('purchaseList.0.sale_data.sale_price', 8000)
                ->where('purchaseList.0.sale_data.channel', '1'));
    }



    #[Test]
    public function getListLoadMoreMergesListTest(): void
    {
        $this->actingAs($this->user);
        Purchase::factory()->count(3)->create(['user_id' => $this->user->id]);

        // 2ページ目は「もっと見る」（一覧だけの部分リロード）で一覧の後ろに継ぎ足す（同じ id は置き換える）
        $url = route('home', ['current_page' => 2, 'page_size' => 2]);
        $version = $this->get($url)->inertiaPage()['version'];
        $response = $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string)$version,
            'X-Inertia-Partial-Component' => 'inventory',
            'X-Inertia-Partial-Data' => 'purchaseList,totalCount',
        ])->get($url);

        //
        $response->assertOk();
        $pageData = $response->json();
        $this->assertSame(3, $pageData['props']['totalCount']);
        $this->assertCount(1, $pageData['props']['purchaseList']);
        $this->assertContains('purchaseList', $pageData['mergeProps']);
        $this->assertContains('purchaseList.id', $pageData['matchPropsOn']);
    }



    #[Test]
    public function getListPartialReloadSkipsOtherPropsTest(): void
    {
        $this->actingAs($this->user);
        Purchase::factory()->create(['user_id' => $this->user->id, 'counterparty_name' => 'ハードオフ西宮店']);

        // 一覧だけを取り直す部分リロードでは、相手方の履歴は返さない
        $this->get(route('home'))
            ->assertInertia(fn(Assert $page) => $page
                ->has('counterpartyList', 1)
                ->reloadOnly(['purchaseList', 'totalCount'], fn(Assert $reload) => $reload
                    ->missing('counterpartyList')
                    ->has('purchaseList', 1)));
    }



    // ============================ update（インライン編集・下書きの完成） ============================

    #[Test]
    public function updateSuccessTest(): void
    {
        $this->actingAs($this->user);
        $purchase = Purchase::factory()->create(['user_id' => $this->user->id, 'item_name' => '旧品名', 'price' => 1000]);

        //
        $response = $this->from(route('home'))->put(route('purchases.update', ['id' => $purchase->id]), $this->confirmedPayload([
            'item_name' => '新品名',
            'price' => 4500,
        ]));

        //
        $response->assertRedirect(route('home'));
        $response->assertInertiaToast('保存しました。');
        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'item_name' => '新品名',
            'price' => 4500,
        ]);
    }



    #[Test]
    public function updateCompletesDraftTest(): void
    {
        $this->actingAs($this->user);
        $draft = Purchase::factory()->draft()->create(['user_id' => $this->user->id]);

        // 下書きを様式15号の必須項目を埋めて確定する
        $this->from(route('home'))->put(route('purchases.update', ['id' => $draft->id]), $this->confirmedPayload())->assertRedirect(route('home'));

        //
        $this->assertDatabaseHas('purchases', ['id' => $draft->id, 'is_draft' => false]);
        $this->get(route('home'))->assertInertia(fn(Assert $page) => $page
            ->where('purchaseList.0.is_draft', false)
            ->where('purchaseList.0.status', 'in_stock'));
    }



    #[Test]
    public function updateValidationErrorTest(): void
    {
        $this->actingAs($this->user);
        $purchase = Purchase::factory()->create(['user_id' => $this->user->id, 'item_name' => '旧品名']);

        // 確定に必要な品目が無ければ保存しない
        $response = $this->from(route('home'))->put(route('purchases.update', ['id' => $purchase->id]), $this->confirmedPayload(['item_name' => '']));

        //
        $response->assertRedirect(route('home'));
        $response->assertSessionHasErrors('item_name');
        $this->assertDatabaseHas('purchases', ['id' => $purchase->id, 'item_name' => '旧品名']);
    }



    #[Test]
    public function updateOtherUsersPurchaseReturns404Test(): void
    {
        $this->actingAs($this->user);
        $othersPurchase = Purchase::factory()->create(['user_id' => $this->otherUser->id]);

        //
        $response = $this->put(route('purchases.update', ['id' => $othersPurchase->id]), $this->confirmedPayload());

        // 他人の仕入は見つからない扱い（404）。値も変わらない
        $response->assertNotFound();
        $this->assertDatabaseHas('purchases', [
            'id' => $othersPurchase->id,
            'item_name' => $othersPurchase->item_name,
        ]);
    }



    #[Test]
    public function updateNotFoundOnPageReturnsBackWithToastTest(): void
    {
        $this->actingAs($this->user);
        $othersPurchase = Purchase::factory()->create(['user_id' => $this->otherUser->id]);

        // 画面（Inertia）からの操作なら、エラー画面にせず元の画面へ戻して理由をトーストで伝える
        $response = $this->withHeader('X-Inertia', 'true')
            ->from(route('home'))
            ->put(route('purchases.update', ['id' => $othersPurchase->id]), $this->confirmedPayload());

        //
        $response->assertRedirect(route('home'));
        $response->assertInertiaToast('対象の仕入が見つかりません。', ToastConsts::ERROR);
        // 操作は失敗扱い（エラー付き）＝画面側の完了処理（モーダルを閉じる等）を走らせない
        $response->assertSessionHasErrors(['request' => '対象の仕入が見つかりません。']);
    }



    #[Test]
    public function getListHasPurchaseTest(): void
    {
        $this->actingAs($this->user);
        // ほかの人の仕入は数えない
        Purchase::factory()->create(['user_id' => $this->otherUser->id]);

        // 仕入が1件もなければ false（画面は「まだ登録がありません」）
        $this->get(route('home'))->assertOk()->assertInertiaProp('hasPurchase', false);

        // 売却済みだけがあり、既定の「在庫中」では0件でも、仕入はあるので true（画面は「この条件に当てはまる仕入はありません」）
        $purchase = Purchase::factory()->create(['user_id' => $this->user->id]);
        Sale::factory()->create(['user_id' => $this->user->id, 'purchase_id' => $purchase->id]);
        $response = $this->get(route('home'));
        $response->assertInertiaCount('purchaseList', 0);
        $response->assertInertiaProp('hasPurchase', true);
    }



    #[Test]
    public function updateSoldPurchaseToDraftIsRefusedTest(): void
    {
        $this->actingAs($this->user);
        $purchase = Purchase::factory()->create(['user_id' => $this->user->id, 'transaction_date' => '2026-07-01', 'item_name' => 'ジャンクカメラ']);
        Sale::factory()->create(['user_id' => $this->user->id, 'purchase_id' => $purchase->id, 'sale_date' => '2026-07-10']);

        // 画面からの操作で、売却済みを下書きに戻そうとする
        $response = $this->withHeader('X-Inertia', 'true')
            ->from(route('home'))
            ->put(route('purchases.update', ['id' => $purchase->id]), $this->confirmedPayload(['is_draft' => true, 'item_name' => null]));

        // 下書きは台帳に載らないため、戻すと払出しの行だけが残ってしまう。元の画面へ戻して理由を伝える
        $response->assertRedirect(route('home'));
        $response->assertInertiaToast('売却済みの仕入は下書きに戻せません。先に売却を取り消してください。', ToastConsts::ERROR);
        $response->assertSessionHasErrors('request');
        $this->assertDatabaseHas('purchases', ['id' => $purchase->id, 'is_draft' => false, 'item_name' => 'ジャンクカメラ']);

        // 台帳は受入れ・払出しの2行のまま（画面遷移として開き直す）
        $this->flushHeaders();
        $this->get(route('ledger'))->assertInertia(fn(Assert $page) => $page
            ->where('totalCount', 2)
            ->where('ledgerList.0.record_type', 'payout')
            ->where('ledgerList.0.item_name', 'ジャンクカメラ')
            ->where('ledgerList.1.record_type', 'receive')
            ->etc());
    }



    #[Test]
    #[DataProvider('data_provider_for_updateSoldPurchaseDateTest')]
    public function updateSoldPurchaseDateTest(string $transactionDate, bool $expectsSuccess): void
    {
        $this->actingAs($this->user);
        $purchase = Purchase::factory()->create(['user_id' => $this->user->id, 'transaction_date' => '2026-07-01']);
        Sale::factory()->create(['user_id' => $this->user->id, 'purchase_id' => $purchase->id, 'sale_date' => '2026-07-10']);

        //
        $response = $this->from(route('home'))
            ->put(route('purchases.update', ['id' => $purchase->id]), $this->confirmedPayload(['transaction_date' => $transactionDate]));

        // 売却済みの仕入日は、売却日（当日を含む）までなら直せる。売却日より後にすると、売ったあとに仕入れたことになる
        $response->assertRedirect(route('home'));
        if ($expectsSuccess === true) {
            $response->assertSessionHasNoErrors();
            $this->assertSame($transactionDate, Purchase::find($purchase->id)->transaction_date->toDateString());
        } else {
            $response->assertSessionHasErrors(['transaction_date' => '売却済みのため、仕入日を売却日（2026-07-10）より後にはできません。']);
            $this->assertSame('2026-07-01', Purchase::find($purchase->id)->transaction_date->toDateString());
        }
    }
    public static function data_provider_for_updateSoldPurchaseDateTest(): array
    {
        return [
            'before_sale_date' => [
                'transactionDate' => '2026-07-05',
                'expectsSuccess' => true,
            ],
            'same_as_sale_date' => [
                'transactionDate' => '2026-07-10',
                'expectsSuccess' => true,
            ],
            'after_sale_date' => [
                'transactionDate' => '2026-07-11',
                'expectsSuccess' => false,
            ],
        ];
    }



    // ============================ delete（論理削除・3年保存） ============================

    #[Test]
    public function deleteSoftDeletesTest(): void
    {
        $this->actingAs($this->user);
        $purchase = Purchase::factory()->create(['user_id' => $this->user->id]);

        //
        $response = $this->from(route('home'))->delete(route('purchases.delete', ['id' => $purchase->id]));

        //
        $response->assertRedirect(route('home'));
        $response->assertInertiaToast('削除しました。');
        // 物理削除ではなく論理削除（法定3年保存）
        $this->assertSoftDeleted('purchases', ['id' => $purchase->id]);
    }



    #[Test]
    public function deleteSoldPurchaseIsRefusedTest(): void
    {
        $this->actingAs($this->user);
        $purchase = Purchase::factory()->create(['user_id' => $this->user->id]);
        Sale::factory()->create(['user_id' => $this->user->id, 'purchase_id' => $purchase->id]);

        // 画面からの操作
        $response = $this->withHeader('X-Inertia', 'true')
            ->from(route('home'))
            ->delete(route('purchases.delete', ['id' => $purchase->id]));

        // 売却済みは削除せず、元の画面へ戻して理由を伝える（台帳に払出しの行だけが残らないように。先に売却取消が必要）
        $response->assertRedirect(route('home'));
        $response->assertInertiaToast('売却済みの仕入は、先に売却を取り消してから削除してください。', ToastConsts::ERROR);
        $response->assertSessionHasErrors('request');
        $this->assertNotSoftDeleted('purchases', ['id' => $purchase->id]);
    }



    #[Test]
    public function deleteAfterCancelingSaleSucceedsTest(): void
    {
        $this->actingAs($this->user);
        $purchase = Purchase::factory()->create(['user_id' => $this->user->id]);
        $sale = Sale::factory()->create(['user_id' => $this->user->id, 'purchase_id' => $purchase->id]);
        // 売却取消（売却の記録を論理削除する）
        $sale->delete();

        //
        $response = $this->from(route('home'))->delete(route('purchases.delete', ['id' => $purchase->id]));

        // 売却を取り消したあとなら削除できる
        $response->assertRedirect(route('home'));
        $response->assertInertiaToast('削除しました。');
        $this->assertSoftDeleted('purchases', ['id' => $purchase->id]);
    }



    #[Test]
    public function deleteOtherUsersPurchaseReturns404Test(): void
    {
        $this->actingAs($this->user);
        $othersPurchase = Purchase::factory()->create(['user_id' => $this->otherUser->id]);

        //
        $response = $this->delete(route('purchases.delete', ['id' => $othersPurchase->id]));

        //
        $response->assertNotFound();
        $this->assertDatabaseHas('purchases', ['id' => $othersPurchase->id, 'deleted_at' => null]);
    }



    // ============================ counterpartyList（相手方チップの履歴サジェスト） ============================

    #[Test]
    public function counterpartyListReturnsDistinctOwnOnlyTest(): void
    {
        $this->actingAs($this->user);

        // 同じ相手方2件（重複排除で1件になる）＋別の相手方1件、別ユーザーの相手方は返らない
        Purchase::factory()->count(2)->create(['user_id' => $this->user->id, 'counterparty_name' => 'ハードオフ西宮店']);
        Purchase::factory()->create(['user_id' => $this->user->id, 'counterparty_name' => 'フリマ太郎']);
        Purchase::factory()->create(['user_id' => $this->otherUser->id, 'counterparty_name' => '他人の店']);

        // 在庫画面では props で常に届く
        $response = $this->get(route('home'));

        //
        $response->assertInertia(fn(Assert $page) => $page
            ->has('counterpartyList', 2)
            ->has('counterpartyList.0', fn(Assert $row) => $row
                ->hasAll(['counterparty_name', 'counterparty_address', 'counterparty_occupation', 'counterparty_age', 'verification_method'])));
        $nameList = array_column($response->inertiaProps('counterpartyList'), 'counterparty_name');
        $this->assertContains('ハードオフ西宮店', $nameList);
        $this->assertContains('フリマ太郎', $nameList);
    }



    #[Test]
    public function counterpartyListOnOtherPagesIsLoadedOnDemandTest(): void
    {
        $this->actingAs($this->user);
        Purchase::factory()->create(['user_id' => $this->user->id, 'counterparty_name' => 'ハードオフ西宮店']);

        // 他の画面では最初は送らず、「＋仕入」を開いた時の部分リロードでだけ返す
        $this->get(route('ledger'))
            ->assertInertia(fn(Assert $page) => $page
                ->component('ledger')
                ->missing('counterpartyList')
                ->reloadOnly('counterpartyList', fn(Assert $reload) => $reload
                    ->has('counterpartyList', 1)
                    ->where('counterpartyList.0.counterparty_name', 'ハードオフ西宮店')));
    }
}
