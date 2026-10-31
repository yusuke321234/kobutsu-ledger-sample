<?php

namespace Tests\Feature;

use App\Models\Purchase;
use App\Models\Sale;
use App\Contracts\Services\SaleServiceInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SaleTest extends TestCase
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
     * 仕入を作る。仕入日は salePayload の売却日（2026-07-10）より前に固定する
     * （Factory の既定は直近6か月のランダムな日付で、売却日より後になることがあるため）
     *
     * @param array $override
     * @return Purchase
     */
    private function createPurchase(array $override): Purchase
    {
        return Purchase::factory()->create(array_merge(['transaction_date' => '2026-07-01'], $override));
    }



    /**
     * 売却の正しいペイロード（画面が送るのと同じ snake_case。テストで一部を上書きして使う）
     *
     * @param string $purchaseId
     * @param array $override
     * @return array
     */
    private function salePayload(string $purchaseId, array $override = []): array
    {
        return array_merge([
            'purchase_id' => $purchaseId,
            'sale_date' => '2026-07-10',
            'channel' => '1',
            'sale_price' => 8000,
            'fee' => 800,
            'shipping_cost' => 200,
        ], $override);
    }



    // ============================ register（「売れた」） ============================

    #[Test]
    public function registerSuccessTest(): void
    {
        $this->actingAs($this->user);
        $purchase = $this->createPurchase(['user_id' => $this->user->id, 'price' => 3000]);

        //
        $response = $this->from(route('home'))->post(route('sales.register'), $this->salePayload((string)$purchase->id));

        //
        $response->assertRedirect(route('home'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('sales', [
            'user_id' => $this->user->id,
            'purchase_id' => $purchase->id,
            'channel' => 1,
            'sale_price' => 8000,
            'fee' => 800,
            'shipping_cost' => 200,
        ]);
        // 単品の素朴粗利（売価8000 − 仕入3000）＝5000
        $response->assertInertiaToast('売却を記録しました。粗利 ¥5,000');
    }



    #[Test]
    public function registerToastWithoutProfitTest(): void
    {
        $this->actingAs($this->user);
        // 仕入額が無い（未入力）なら粗利は出せない
        $purchase = $this->createPurchase(['user_id' => $this->user->id, 'price' => null]);

        //
        $response = $this->from(route('home'))->post(route('sales.register'), $this->salePayload((string)$purchase->id));

        //
        $response->assertRedirect(route('home'));
        $response->assertInertiaToast('売却を記録しました。');
    }



    #[Test]
    public function registerToastWithNegativeProfitTest(): void
    {
        $this->actingAs($this->user);
        $purchase = $this->createPurchase(['user_id' => $this->user->id, 'price' => 9000]);

        // 赤字はマイナスで伝える
        $this->from(route('home'))->post(route('sales.register'), $this->salePayload((string)$purchase->id, ['sale_price' => 8000]))
            ->assertInertiaToast('売却を記録しました。粗利 ¥-1,000');
    }



    #[Test]
    public function registerFeesDefaultToZeroTest(): void
    {
        $this->actingAs($this->user);
        $purchase = $this->createPurchase(['user_id' => $this->user->id]);

        // 手数料・送料は未入力なら 0 として記録する
        $this->from(route('home'))->post(route('sales.register'), $this->salePayload((string)$purchase->id, [
            'fee' => null,
            'shipping_cost' => null,
        ]))->assertRedirect(route('home'))->assertSessionHasNoErrors();

        //
        $this->assertDatabaseHas('sales', ['purchase_id' => $purchase->id, 'fee' => 0, 'shipping_cost' => 0]);
    }



    #[Test]
    public function registerRequiresAuthTest(): void
    {
        $purchase = $this->createPurchase(['user_id' => $this->user->id]);
        $this->post(route('sales.register'), $this->salePayload((string)$purchase->id))->assertRedirect(route('login'));
        $this->assertDatabaseCount('sales', 0);
    }



    #[Test]
    public function cannotSellSamePurchaseTwiceTest(): void
    {
        $this->actingAs($this->user);
        $purchase = $this->createPurchase(['user_id' => $this->user->id]);
        $this->from(route('home'))->post(route('sales.register'), $this->salePayload((string)$purchase->id))->assertSessionHasNoErrors();

        // 1仕入＝1売却
        $response = $this->from(route('home'))->post(route('sales.register'), $this->salePayload((string)$purchase->id));

        //
        $response->assertRedirect(route('home'));
        $response->assertSessionHasErrors([
            'purchase_id' => 'この商品はすでに売却済みです（1つの仕入に対し売却は1件までです）。',
        ]);
        $this->assertDatabaseCount('sales', 1);
    }



    #[Test]
    public function cannotSellOtherUsersPurchaseTest(): void
    {
        $this->actingAs($this->user);
        $othersPurchase = $this->createPurchase(['user_id' => $this->otherUser->id]);

        //
        $response = $this->from(route('home'))->post(route('sales.register'), $this->salePayload((string)$othersPurchase->id));

        //
        $response->assertSessionHasErrors([
            'purchase_id' => 'この商品は売却できません（見つからないか、下書きのままです）。',
        ]);
        $this->assertDatabaseCount('sales', 0);
    }



    #[Test]
    public function cannotSellDraftPurchaseTest(): void
    {
        $this->actingAs($this->user);
        // 下書き＝必須項目が未入力の未完成記録なので売却させない
        $draft = Purchase::factory()->draft()->create(['user_id' => $this->user->id]);

        //
        $response = $this->from(route('home'))->post(route('sales.register'), $this->salePayload((string)$draft->id));

        //
        $response->assertSessionHasErrors('purchase_id');
        $this->assertDatabaseCount('sales', 0);
    }



    #[Test]
    public function registerValidatesInputTest(): void
    {
        $this->actingAs($this->user);
        $purchase = $this->createPurchase(['user_id' => $this->user->id]);

        // 販路が不正
        $this->from(route('home'))->post(route('sales.register'), $this->salePayload((string)$purchase->id, ['channel' => '99']))
            ->assertSessionHasErrors('channel');
        // 売価が無い
        $payload = $this->salePayload((string)$purchase->id);
        unset($payload['sale_price']);
        $this->from(route('home'))->post(route('sales.register'), $payload)->assertSessionHasErrors('sale_price');
        // 売却日が無い
        $payload = $this->salePayload((string)$purchase->id);
        unset($payload['sale_date']);
        $this->from(route('home'))->post(route('sales.register'), $payload)->assertSessionHasErrors('sale_date');

        //
        $this->assertDatabaseCount('sales', 0);
    }



    #[Test]
    public function registerErrorsReachSaleFormAsSnakeCaseTest(): void
    {
        $this->actingAs($this->user);
        $purchase = $this->createPurchase(['user_id' => $this->user->id]);
        $this->from(route('home'))->post(route('sales.register'), $this->salePayload((string)$purchase->id))->assertSessionHasNoErrors();

        // 二重売却のエラーは、売却モーダルの項目名（purchase_id）で届く
        $this->from(route('home'))->post(route('sales.register'), $this->salePayload((string)$purchase->id));
        $this->get(route('home'))->assertInertia(fn(Assert $page) => $page
            ->has('errors.purchase_id')
            ->missing('errors.purchaseId')
            ->etc());
    }



    #[Test]
    #[DataProvider('data_provider_for_registerSaleDateTest')]
    public function registerSaleDateTest(string $saleDate, bool $expectsSuccess): void
    {
        $this->actingAs($this->user);
        $purchase = $this->createPurchase(['user_id' => $this->user->id, 'transaction_date' => '2026-07-05']);

        //
        $response = $this->from(route('home'))
            ->post(route('sales.register'), $this->salePayload((string)$purchase->id, ['sale_date' => $saleDate]));

        // 売却日は仕入日（当日を含む）以降。仕入日より前だと、仕入れる前に売ったことになる
        $response->assertRedirect(route('home'));
        if ($expectsSuccess === true) {
            $response->assertSessionHasNoErrors();
            $this->assertDatabaseCount('sales', 1);
        } else {
            $response->assertSessionHasErrors(['sale_date' => '売却日は仕入日（2026-07-05）より前にできません。']);
            $this->assertDatabaseCount('sales', 0);
        }
    }
    public static function data_provider_for_registerSaleDateTest(): array
    {
        return [
            'same_as_purchase_date' => [
                'saleDate' => '2026-07-05',
                'expectsSuccess' => true,
            ],
            'before_purchase_date' => [
                'saleDate' => '2026-07-04',
                'expectsSuccess' => false,
            ],
        ];
    }



    #[Test]
    public function registerTwiceInServiceIsRefusedTest(): void
    {
        $purchase = $this->createPurchase(['user_id' => $this->user->id]);
        $saleService = app(SaleServiceInterface::class);
        $registerData = [
            'purchase_id' => (string)$purchase->id,
            'sale_date' => '2026-07-10',
            'channel' => '1',
            'sale_price' => 8000,
            'fee' => 0,
            'shipping_cost' => 0,
            'counterparty_name' => null,
            'counterparty_address' => null,
        ];

        // 二重送信で2件とも入力チェックを通ってしまった場合を再現する（入力チェックを通さず Service を2回呼ぶ）。
        // 2件目は仕入の行のロックが解けてから確かめ直すので、売却済みとして登録しない
        $firstResult = $saleService->register((string)$this->user->id, $registerData);
        $secondResult = $saleService->register((string)$this->user->id, $registerData);

        //
        $this->assertSame('ok', $firstResult['status']);
        $this->assertSame('sold', $secondResult['status']);
        $this->assertDatabaseCount('sales', 1);
    }



    // ============================ 記録するだけで在庫・台帳が動く（コアの約束） ============================

    #[Test]
    public function sellingMovesItemToSoldInInventoryTest(): void
    {
        $this->actingAs($this->user);
        $purchase = $this->createPurchase(['user_id' => $this->user->id, 'price' => 3000]);

        // 売却前は在庫中
        $this->get(route('home', ['status' => 'in_stock']))->assertInertia(fn(Assert $page) => $page->where('totalCount', 1)->etc());

        //
        $this->from(route('home'))->post(route('sales.register'), $this->salePayload((string)$purchase->id))->assertRedirect(route('home'));

        // 売却後は在庫中から消え、売却済に移る（状態は sale の有無から導出される）
        $this->get(route('home', ['status' => 'in_stock']))->assertInertia(fn(Assert $page) => $page->where('totalCount', 0)->etc());
        $this->get(route('home', ['status' => 'sold']))->assertInertia(fn(Assert $page) => $page
            ->where('totalCount', 1)
            ->where('purchaseList.0.status', 'sold')
            ->where('purchaseList.0.raw_gross_profit', 5000)
            ->etc());
    }



    #[Test]
    public function sellingCreatesPayoutRowInLedgerTest(): void
    {
        $this->actingAs($this->user);
        $purchase = Purchase::factory()->create([
            'user_id' => $this->user->id,
            'transaction_date' => '2026-07-01',
            'item_name' => 'ジャンクカメラ',
        ]);

        // 売却前の台帳は受入1行だけ
        $this->get(route('ledger'))->assertInertia(fn(Assert $page) => $page->where('totalCount', 1)->etc());

        //
        $this->from(route('home'))->post(route('sales.register'), $this->salePayload((string)$purchase->id))->assertRedirect(route('home'));

        // 売却を記録するだけで、台帳の払出行が自動でできる（台帳のための入力は不要）
        $this->get(route('ledger'))->assertInertia(fn(Assert $page) => $page
            ->where('totalCount', 2)
            ->where('ledgerList.0.record_type', 'payout')
            ->where('ledgerList.0.transaction_date', '2026-07-10')
            ->where('ledgerList.0.item_name', 'ジャンクカメラ')
            ->where('ledgerList.1.record_type', 'receive')
            ->etc());
    }



    // ============================ update / delete ============================

    #[Test]
    public function updateSuccessTest(): void
    {
        $this->actingAs($this->user);
        $purchase = $this->createPurchase(['user_id' => $this->user->id, 'price' => 3000]);
        $sale = Sale::factory()->create(['user_id' => $this->user->id, 'purchase_id' => $purchase->id, 'sale_price' => 5000]);

        // 売価を訂正する
        $response = $this->from(route('home'))->put(route('sales.update', ['id' => $sale->id]), [
            'sale_date' => '2026-07-11',
            'channel' => '2',
            'sale_price' => 9000,
        ]);

        //
        $response->assertRedirect(route('home'));
        $response->assertInertiaToast('売却を更新しました。');
        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'sale_price' => 9000, 'channel' => 2]);
        $this->get(route('home', ['status' => 'sold']))->assertInertia(fn(Assert $page) => $page
            ->where('purchaseList.0.sale_data.sale_price', 9000)
            ->where('purchaseList.0.sale_data.channel', '2')
            ->where('purchaseList.0.raw_gross_profit', 6000)
            ->etc());
    }



    #[Test]
    public function updateSaleDateBeforePurchaseDateIsRefusedTest(): void
    {
        $this->actingAs($this->user);
        $purchase = $this->createPurchase(['user_id' => $this->user->id, 'transaction_date' => '2026-07-05']);
        $sale = Sale::factory()->create(['user_id' => $this->user->id, 'purchase_id' => $purchase->id, 'sale_date' => '2026-07-10']);

        // 売却日を仕入日より前に訂正しようとする
        $response = $this->from(route('home'))->put(route('sales.update', ['id' => $sale->id]), [
            'sale_date' => '2026-07-04',
            'channel' => '1',
            'sale_price' => 8000,
        ]);

        //
        $response->assertRedirect(route('home'));
        $response->assertSessionHasErrors(['sale_date' => '売却日は仕入日（2026-07-05）より前にできません。']);
        $this->assertSame('2026-07-10', Sale::find($sale->id)->sale_date->toDateString());
    }



    #[Test]
    public function updateOtherUsersSaleReturns404Test(): void
    {
        $this->actingAs($this->user);
        $othersPurchase = $this->createPurchase(['user_id' => $this->otherUser->id]);
        $othersSale = Sale::factory()->create(['user_id' => $this->otherUser->id, 'purchase_id' => $othersPurchase->id]);

        //
        $response = $this->put(route('sales.update', ['id' => $othersSale->id]), [
            'sale_date' => '2026-07-11',
            'channel' => '1',
            'sale_price' => 9000,
        ]);

        //
        $response->assertNotFound();
        $this->assertDatabaseMissing('sales', ['id' => $othersSale->id, 'sale_price' => 9000]);
    }



    #[Test]
    public function deleteRevertsItemToInStockTest(): void
    {
        $this->actingAs($this->user);
        $purchase = $this->createPurchase(['user_id' => $this->user->id]);
        $sale = Sale::factory()->create(['user_id' => $this->user->id, 'purchase_id' => $purchase->id]);

        //
        $response = $this->from(route('home', ['status' => 'sold']))->delete(route('sales.delete', ['id' => $sale->id]));

        // 表示中の画面（絞り込みを含む）へ戻す
        $response->assertRedirect(route('home', ['status' => 'sold']));
        $response->assertInertiaToast('売却を取り消しました。');
        // 物理削除ではなく論理削除（法定3年保存）
        $this->assertSoftDeleted('sales', ['id' => $sale->id]);
        // 売却の取り消し＝商品は在庫中に戻り、台帳の払出行も消える
        $this->get(route('home', ['status' => 'in_stock']))->assertInertia(fn(Assert $page) => $page->where('totalCount', 1)->etc());
        $this->get(route('ledger'))->assertInertia(fn(Assert $page) => $page
            ->where('totalCount', 1)
            ->where('ledgerList.0.record_type', 'receive')
            ->etc());
    }



    #[Test]
    public function canSellAgainAfterCancellingSaleTest(): void
    {
        $this->actingAs($this->user);
        $purchase = $this->createPurchase(['user_id' => $this->user->id]);
        $sale = Sale::factory()->create(['user_id' => $this->user->id, 'purchase_id' => $purchase->id]);
        $this->from(route('home'))->delete(route('sales.delete', ['id' => $sale->id]))->assertRedirect(route('home'));

        // 売却を取り消した後は、同じ商品をもう一度売却できる（1仕入=1売却は「生きている売却」で判定）
        $response = $this->from(route('home'))->post(route('sales.register'), $this->salePayload((string)$purchase->id));

        //
        $response->assertRedirect(route('home'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('sales', 2);
    }



    #[Test]
    public function deleteOtherUsersSaleReturns404Test(): void
    {
        $this->actingAs($this->user);
        $othersPurchase = $this->createPurchase(['user_id' => $this->otherUser->id]);
        $othersSale = Sale::factory()->create(['user_id' => $this->otherUser->id, 'purchase_id' => $othersPurchase->id]);

        //
        $this->delete(route('sales.delete', ['id' => $othersSale->id]))->assertNotFound();
        $this->assertDatabaseHas('sales', ['id' => $othersSale->id, 'deleted_at' => null]);
    }
}
