<?php

namespace Tests\Feature;

use App\Enums\Kubun;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LedgerTest extends TestCase
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
     * 台帳画面を取得する（画面が送るのと同じ snake_case のクエリ。ページングは既定値で埋める）
     *
     * @param array $queryData
     * @return TestResponse
     */
    private function getLedger(array $queryData = []): TestResponse
    {
        $query = array_merge(['current_page' => 1, 'page_size' => 20], $queryData);
        return $this->get(route('ledger', $query));
    }



    // ============================ 基本（認証・所有者スコープ・検証） ============================

    #[Test]
    #[DataProvider('data_provider_for_getListValidationTest')]
    public function getListValidationTest(string $testCase, array $queryData, string $expected): void
    {
        // 認証状態を用意（未認証ケースのみ actingAs しない）
        if ($testCase === 'unauthenticated') {
            // no action.
        } else {
            $this->actingAs($this->user);
        }

        //
        $response = $this->get(route('ledger', $queryData));

        //
        if ($expected === 'ok') {
            $response->assertOk()->assertInertia(fn(Assert $page) => $page
                ->component('ledger')
                ->where('filterData', ['from' => null, 'to' => null, 'q' => ''])
                ->where('pageSize', 20)
                ->etc());
        } elseif ($expected === 'unauthenticated') {
            $response->assertRedirect(route('login'));
        } else {
            // 不正なクエリは、クエリを外した台帳へ戻す（同じ失敗を繰り返さない）
            $response->assertRedirect(route('ledger'));
            $response->assertSessionHasErrors($expected);
        }
    }
    public static function data_provider_for_getListValidationTest(): array
    {
        return [
            'unauthenticated' => [
                'testCase' => 'unauthenticated',
                'queryData' => [],
                'expected' => 'unauthenticated',
            ],
            'success' => [
                'testCase' => 'success',
                'queryData' => ['current_page' => 1, 'page_size' => 20],
                'expected' => 'ok',
            ],
            // ページングは省略できる（1ページ目・20件）
            'default_pagination' => [
                'testCase' => 'default_pagination',
                'queryData' => [],
                'expected' => 'ok',
            ],
            'invalid_date' => [
                'testCase' => 'invalid_date',
                'queryData' => ['from' => 'あ'],
                'expected' => 'from',
            ],
            // 日付は YYYY-MM-DD だけを受け付ける（出力のファイル名にも使うため、形をそろえる）
            'not_iso_date' => [
                'testCase' => 'not_iso_date',
                'queryData' => ['from' => '2026/07/01'],
                'expected' => 'from',
            ],
            // 期間の前後が逆
            'to_before_from' => [
                'testCase' => 'to_before_from',
                'queryData' => ['from' => '2026-07-10', 'to' => '2026-07-01'],
                'expected' => 'to',
            ],
        ];
    }



    #[Test]
    public function getListReturnsOwnRecordsOnlyTest(): void
    {
        $this->actingAs($this->user);
        Purchase::factory()->create(['user_id' => $this->user->id]);
        Purchase::factory()->create(['user_id' => $this->otherUser->id]);

        //
        $response = $this->getLedger();

        // 他人の記録は台帳に出ない
        $response->assertOk();
        $response->assertInertiaProp('totalCount', 1);
    }



    // ============================ 受入＋払出の時系列1表（台帳の核） ============================

    #[Test]
    public function getListMergesReceiveAndPayoutInDateOrderTest(): void
    {
        $this->actingAs($this->user);

        // 7/1 受入・7/5 受入・7/10 払出（7/1 の品を売却）
        $oldPurchase = Purchase::factory()->create([
            'user_id' => $this->user->id,
            'transaction_date' => '2026-07-01',
            'item_name' => 'ジャンクカメラ',
            'quantity' => 2,
            'features' => 'Nikon F3・並品',
        ]);
        Purchase::factory()->create([
            'user_id' => $this->user->id,
            'transaction_date' => '2026-07-05',
            'item_name' => '古本セット',
        ]);
        Sale::factory()->create([
            'user_id' => $this->user->id,
            'purchase_id' => $oldPurchase->id,
            'sale_date' => '2026-07-10',
            'channel' => '1',
            'sale_price' => 8000,
        ]);

        //
        $response = $this->getLedger();

        // 受入2 + 払出1 = 3行が取引年月日の降順で1表になる
        $response->assertOk();
        $response->assertInertiaProp('totalCount', 3);
        $response->assertInertiaProp('ledgerList.0.transaction_date', '2026-07-10');
        $response->assertInertiaProp('ledgerList.0.record_type', 'payout');
        $response->assertInertiaProp('ledgerList.1.transaction_date', '2026-07-05');
        $response->assertInertiaProp('ledgerList.1.record_type', 'receive');
        $response->assertInertiaProp('ledgerList.2.transaction_date', '2026-07-01');
        $response->assertInertiaProp('ledgerList.2.record_type', 'receive');

        // 払出の品目・数量・特徴は紐づく仕入から引く（売却金額は sale 側）
        $response->assertInertiaProp('ledgerList.0.item_name', 'ジャンクカメラ');
        $response->assertInertiaProp('ledgerList.0.quantity', 2);
        $response->assertInertiaProp('ledgerList.0.features', 'Nikon F3・並品');
        $response->assertInertiaProp('ledgerList.0.amount', 8000);
        $response->assertInertiaProp('ledgerList.0.channel', '1');
        // 払出に区分・確認方法は無い
        $response->assertInertiaProp('ledgerList.0.kubun', null);
        $response->assertInertiaProp('ledgerList.0.verification_method', null);
        $response->assertInertiaProp('ledgerList.0.purchase_id', (string)$oldPurchase->id);
    }



    #[Test]
    public function receiveRowHasStatutoryColumnsTest(): void
    {
        $this->actingAs($this->user);
        Purchase::factory()->create([
            'user_id' => $this->user->id,
            'transaction_date' => '2026-07-01',
            'kubun' => '1',
            'item_name' => 'ジャンクカメラ',
            'quantity' => 2,
            'features' => 'Nikon F3・並品',
            'counterparty_name' => 'ハードオフ西宮店',
            'counterparty_address' => '兵庫県西宮市',
            'counterparty_occupation' => '古物商',
            'counterparty_age' => '40',
            'verification_method' => '4',
            'price' => 3000,
        ]);

        //
        $response = $this->getLedger();

        // 様式15号の必須項目（取引年月日・区分・品目/数量・特徴・相手方4項目・確認方法）が揃う
        $response->assertOk();
        $response->assertInertiaProp('ledgerList.0.record_type', 'receive');
        $response->assertInertiaProp('ledgerList.0.transaction_date', '2026-07-01');
        $response->assertInertiaProp('ledgerList.0.kubun', '1');
        $response->assertInertiaProp('ledgerList.0.item_name', 'ジャンクカメラ');
        $response->assertInertiaProp('ledgerList.0.quantity', 2);
        $response->assertInertiaProp('ledgerList.0.features', 'Nikon F3・並品');
        $response->assertInertiaProp('ledgerList.0.counterparty_name', 'ハードオフ西宮店');
        $response->assertInertiaProp('ledgerList.0.counterparty_address', '兵庫県西宮市');
        $response->assertInertiaProp('ledgerList.0.counterparty_occupation', '古物商');
        $response->assertInertiaProp('ledgerList.0.counterparty_age', '40');
        $response->assertInertiaProp('ledgerList.0.verification_method', '4');
        $response->assertInertiaProp('ledgerList.0.amount', 3000);
    }



    // ============================ 台帳に載せないもの ============================

    #[Test]
    public function draftIsNotListedInLedgerTest(): void
    {
        $this->actingAs($this->user);
        Purchase::factory()->create(['user_id' => $this->user->id, 'transaction_date' => '2026-07-01']);
        // 下書き＝必須項目が未入力の未完成記録なので法定台帳には載せない
        Purchase::factory()->draft()->create(['user_id' => $this->user->id, 'transaction_date' => '2026-07-02']);

        //
        $response = $this->getLedger();

        //
        $response->assertOk();
        $response->assertInertiaProp('totalCount', 1);
        $response->assertInertiaProp('ledgerList.0.transaction_date', '2026-07-01');
    }



    #[Test]
    public function softDeletedPurchaseIsNotListedTest(): void
    {
        $this->actingAs($this->user);
        $purchase = Purchase::factory()->create(['user_id' => $this->user->id]);
        Purchase::factory()->create(['user_id' => $this->user->id]);
        $purchase->delete();

        //
        $response = $this->getLedger();

        // 論理削除は3年保存のためDBに残るが、台帳表示からは外す
        $response->assertOk();
        $response->assertInertiaProp('totalCount', 1);
    }



    // ============================ 期間絞り込み・検索 ============================

    #[Test]
    public function getListFiltersByPeriodTest(): void
    {
        $this->actingAs($this->user);
        Purchase::factory()->create(['user_id' => $this->user->id, 'transaction_date' => '2026-06-30']);
        Purchase::factory()->create(['user_id' => $this->user->id, 'transaction_date' => '2026-07-05']);
        Purchase::factory()->create(['user_id' => $this->user->id, 'transaction_date' => '2026-07-31']);

        // 7/1〜7/10 に入るのは 7/5 の1件だけ（境界は両端を含む）
        $this->getLedger(['from' => '2026-07-01', 'to' => '2026-07-10'])
            ->assertOk()
            ->assertInertiaProp('totalCount', 1)
            ->assertInertiaProp('ledgerList.0.transaction_date', '2026-07-05');

        // 期間未指定は全期間
        $this->getLedger()->assertOk()->assertInertiaProp('totalCount', 3);
    }



    #[Test]
    public function getListPeriodIncludesBoundaryDatesTest(): void
    {
        $this->actingAs($this->user);
        Purchase::factory()->create(['user_id' => $this->user->id, 'transaction_date' => '2026-07-01']);
        Purchase::factory()->create(['user_id' => $this->user->id, 'transaction_date' => '2026-07-31']);

        // from/to と同日の記録も台帳に含む
        $this->getLedger(['from' => '2026-07-01', 'to' => '2026-07-31'])
            ->assertOk()
            ->assertInertiaProp('totalCount', 2);
    }



    #[Test]
    public function getListSearchesReceiveAndPayoutTest(): void
    {
        $this->actingAs($this->user);

        $camera = Purchase::factory()->create([
            'user_id' => $this->user->id,
            'transaction_date' => '2026-07-01',
            'item_name' => 'ライカのカメラ',
        ]);
        Purchase::factory()->create([
            'user_id' => $this->user->id,
            'transaction_date' => '2026-07-02',
            'item_name' => '古本',
        ]);
        // カメラの払出（品目は仕入側にあるが、検索でヒットする必要がある）
        Sale::factory()->create([
            'user_id' => $this->user->id,
            'purchase_id' => $camera->id,
            'sale_date' => '2026-07-10',
        ]);

        // 「カメラ」で受入1 + 払出1 = 2件（払出は紐づく仕入の品目で拾う）
        $response = $this->getLedger(['q' => 'カメラ']);
        $response->assertOk();
        $response->assertInertiaProp('totalCount', 2);
        $response->assertInertiaProp('ledgerList.0.record_type', 'payout');
        $response->assertInertiaProp('ledgerList.1.record_type', 'receive');
    }



    #[Test]
    public function getListPaginatesMergedRowsTest(): void
    {
        $this->actingAs($this->user);
        Purchase::factory()->create(['user_id' => $this->user->id, 'transaction_date' => '2026-07-01']);
        Purchase::factory()->create(['user_id' => $this->user->id, 'transaction_date' => '2026-07-02']);
        Purchase::factory()->create(['user_id' => $this->user->id, 'transaction_date' => '2026-07-03']);

        // 総件数は3のまま、1ページ2件で切り出す
        $this->getLedger(['current_page' => 1, 'page_size' => 2])
            ->assertOk()
            ->assertInertiaProp('totalCount', 3)
            ->assertInertiaCount('ledgerList', 2)
            ->assertInertiaProp('ledgerList.0.transaction_date', '2026-07-03');

        $this->getLedger(['current_page' => 2, 'page_size' => 2])
            ->assertOk()
            ->assertInertiaProp('totalCount', 3)
            ->assertInertiaCount('ledgerList', 1)
            ->assertInertiaProp('ledgerList.0.transaction_date', '2026-07-01');
    }



    // ============================ 様式15号 PDF / CSV 出力（法要件） ============================

    #[Test]
    public function exportPdfRequiresAuthTest(): void
    {
        // 未ログインはログイン画面へ（ファイルは渡さない）
        $this->get(route('ledger.pdf'))->assertRedirect(route('login'));
        $this->get(route('ledger.csv'))->assertRedirect(route('login'));
    }



    #[Test]
    public function exportPdfReturnsInlinePdfTest(): void
    {
        $this->actingAs($this->user);
        $this->user->forceFill(['office_name' => 'コブチョウ商店', 'license_number' => '第123456789012号'])->save();
        Purchase::factory()->create([
            'user_id' => $this->user->id,
            'transaction_date' => '2026-07-01',
            'item_name' => 'ジャンクカメラ',
        ]);

        //
        $response = $this->get(route('ledger.pdf', ['from' => '2026-07-01', 'to' => '2026-07-31']));

        //
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        // 立入時に「直ちに書面に表示」＝ダウンロードでなく inline で開く
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));

        // 実体が PDF であること
        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF', $content);
        $this->assertGreaterThan(1000, strlen($content));
    }



    #[Test]
    public function exportPdfWorksWithoutOfficeSettingsTest(): void
    {
        $this->actingAs($this->user);
        // 営業所名・許可番号が未設定でも、記録が消えるより出力できる方が安全（PDF側で未設定を明示）
        Purchase::factory()->create(['user_id' => $this->user->id]);

        //
        $response = $this->get(route('ledger.pdf'));

        //
        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }



    #[Test]
    public function exportPdfWithNoRecordsStillReturnsPdfTest(): void
    {
        $this->actingAs($this->user);

        // 0件でも「記録なし」の帳票が出る（立入時に何も出せない状態を作らない）
        $response = $this->get(route('ledger.pdf'));

        //
        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }



    #[Test]
    public function exportCsvContainsStatutoryColumnsTest(): void
    {
        $this->actingAs($this->user);
        Purchase::factory()->create([
            'user_id' => $this->user->id,
            'transaction_date' => '2026-07-01',
            'kubun' => '1',
            'item_name' => 'ジャンクカメラ',
            'quantity' => 2,
            'features' => 'Nikon F3',
            'counterparty_name' => 'ハードオフ西宮店',
            'counterparty_address' => '兵庫県西宮市',
            'counterparty_occupation' => '古物商',
            'counterparty_age' => '40',
            'verification_method' => '4',
            'price' => 3000,
        ]);

        //
        $response = $this->get(route('ledger.csv'));
        $content = $response->getContent();

        //
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));

        // Excel（日本語環境）で文字化けしないよう UTF-8 BOM 付き
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        // 法定の記載事項が列として揃う
        $this->assertStringContainsString('取引年月日', $content);
        $this->assertStringContainsString('区別', $content);
        $this->assertStringContainsString('確認方法', $content);
        // 中身（区別・確認方法は表示名に変換される）
        $this->assertStringContainsString('ジャンクカメラ', $content);
        $this->assertStringContainsString('買受', $content);
        $this->assertStringContainsString('古物商・店舗からの仕入', $content);
        $this->assertStringContainsString('ハードオフ西宮店', $content);
    }



    #[Test]
    public function exportCsvIncludesPayoutAndExcludesOthersTest(): void
    {
        $this->actingAs($this->user);
        $purchase = Purchase::factory()->create([
            'user_id' => $this->user->id,
            'transaction_date' => '2026-07-01',
            'item_name' => '自分のカメラ',
        ]);
        Sale::factory()->create([
            'user_id' => $this->user->id,
            'purchase_id' => $purchase->id,
            'sale_date' => '2026-07-10',
            'sale_price' => 8000,
        ]);
        // 他人の記録・自分の下書きは載せない
        Purchase::factory()->create(['user_id' => $this->otherUser->id, 'item_name' => '他人のカメラ']);
        Purchase::factory()->draft()->create(['user_id' => $this->user->id, 'item_name' => null]);

        //
        $content = $this->get(route('ledger.csv'))->getContent();

        // 受入れ/払出しの別（法令上の必須の区別）が1表の各行に出る
        $this->assertStringContainsString('受入/払出', $content);
        $this->assertStringContainsString('受入れ', $content);
        $this->assertStringContainsString('払出し', $content);
        $this->assertStringContainsString('売却', $content);
        $this->assertStringContainsString('自分のカメラ', $content);
        $this->assertStringNotContainsString('他人のカメラ', $content);
    }



    #[Test]
    public function exportCsvLabelsConsignmentPayoutAsHikiwatashiTest(): void
    {
        $this->actingAs($this->user);
        // 委託で受け入れた品を売ると、払出しの区別は法令上「委託に基づく引渡し」（売却ではない）
        $purchase = Purchase::factory()->create([
            'user_id' => $this->user->id,
            'transaction_date' => '2026-07-01',
            'item_name' => '委託のカメラ',
            'kubun' => Kubun::CONSIGN,
        ]);
        Sale::factory()->create([
            'user_id' => $this->user->id,
            'purchase_id' => $purchase->id,
            'sale_date' => '2026-07-10',
            'sale_price' => 8000,
        ]);

        //
        $content = $this->get(route('ledger.csv'))->getContent();

        // 受入れ行は「委託」、払出し行は「委託に基づく引渡し」
        $this->assertStringContainsString('委託', $content);
        $this->assertStringContainsString('委託に基づく引渡し', $content);
    }



    #[Test]
    public function exportCsvFiltersByPeriodTest(): void
    {
        $this->actingAs($this->user);
        Purchase::factory()->create(['user_id' => $this->user->id, 'transaction_date' => '2026-06-30', 'item_name' => '期間外の品']);
        Purchase::factory()->create(['user_id' => $this->user->id, 'transaction_date' => '2026-07-31', 'item_name' => '期間内の品']);

        //
        $content = $this->get(route('ledger.csv', ['from' => '2026-07-01', 'to' => '2026-07-31']))->getContent();

        // 終端日（7/31）も含まれる。期間外は出ない
        $this->assertStringContainsString('期間内の品', $content);
        $this->assertStringNotContainsString('期間外の品', $content);
    }



    #[Test]
    public function exportCsvNeutralizesFormulaTest(): void
    {
        $this->actingAs($this->user);
        Purchase::factory()->create([
            'user_id' => $this->user->id,
            'transaction_date' => '2026-07-01',
            'item_name' => '=HYPERLINK("https://example.com","x")',
            'features' => '+1',
            'counterparty_name' => '@SUM(A1)',
            'counterparty_address' => '-2+3',
        ]);
        Purchase::factory()->create([
            'user_id' => $this->user->id,
            'transaction_date' => '2026-07-02',
            'item_name' => 'Nikon F3',
        ]);

        //
        $content = $this->get(route('ledger.csv'))->getContent();

        // = + - @ で始まる文字は、表計算ソフトに数式として読まれないよう先頭に ' を付ける。ふつうの文字はそのまま
        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertStringContainsString("'+1", $content);
        $this->assertStringContainsString("'@SUM(A1)", $content);
        $this->assertStringContainsString("'-2+3", $content);
        $this->assertStringContainsString('Nikon F3', $content);
        $this->assertStringNotContainsString("'Nikon F3", $content);
    }



    #[Test]
    public function getListSearchTreatsPercentLiterallyTest(): void
    {
        $this->actingAs($this->user);
        $halfOff = Purchase::factory()->create(['user_id' => $this->user->id, 'transaction_date' => '2026-07-01', 'item_name' => '50%オフの品', 'features' => null, 'counterparty_name' => null]);
        Purchase::factory()->create(['user_id' => $this->user->id, 'transaction_date' => '2026-07-02', 'item_name' => '500円の品', 'features' => null, 'counterparty_name' => null]);
        Sale::factory()->create(['user_id' => $this->user->id, 'purchase_id' => $halfOff->id, 'sale_date' => '2026-07-10', 'counterparty_name' => null]);

        // 「50%」は 500円の品には当たらない。受入1 + 払出1 = 2件
        $response = $this->getLedger(['q' => '50%']);
        $response->assertOk();
        $response->assertInertiaProp('totalCount', 2);
    }
}
