<?php

namespace Database\Seeders;

use App\Enums\Kubun;
use App\Enums\SalesChannel;
use App\Enums\VerificationMethod;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * 手元で画面を触るためのサンプルデータ（本番では使わない）。
 *
 * `php artisan migrate:fresh --seed` で次のアカウントができる（パスワードは password）。
 * - demo@example.com：営業所名・許可番号を設定済み（台帳の PDF にそのまま載る）
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** 相手方（名前・住所・職業・年齢・確認方法）。チップの履歴に並ぶ */
    private const COUNTERPARTY_LIST = [
        ['山田商店', '東京都台東区浅草1-2-3', '古物商', '52', VerificationMethod::DEALER],
        ['佐藤 花子', '神奈川県横浜市中区4-5-6', '会社員', '34', VerificationMethod::LICENSE],
        ['鈴木 一郎', '埼玉県さいたま市浦和区7-8-9', '自営業', '61', VerificationMethod::MYNUMBER_CARD],
    ];

    /** 在庫中（品目・特徴・仕入額・何日前・相手方の番号） */
    private const IN_STOCK_LIST = [
        ['Nikon F3 ボディ', 'シリアル 1234567・シャッター動作確認済', 28000, 3, 0],
        ['SEIKO 5 自動巻き腕時計', '裏蓋に小傷あり', 6500, 5, 1],
        ['Canon AE-1 PROGRAM', '50mm F1.8 付き', 15000, 9, 0],
        ['ルイ・ヴィトン 長財布', 'モノグラム・角スレあり', 12000, 12, 2],
        ['任天堂 ゲームボーイ本体', 'DMG-01・画面焼けなし', 4800, 20, 1],
        ['CASIO G-SHOCK DW-5600', '電池交換済', 7200, 34, 2],
    ];

    /** 売却済（品目・仕入額・何日前に仕入・何日前に売却・販路・売価・手数料・送料・相手方の番号） */
    private const SOLD_LIST = [
        ['PENTAX SP ボディ', 5000, 60, 40, SalesChannel::MERCARI, 12800, 1280, 850, 0],
        ['OMEGA シーマスター', 45000, 90, 45, SalesChannel::YAHOO_AUCTION, 78000, 7722, 1200, 2],
        ['コーチ ショルダーバッグ', 3500, 50, 20, SalesChannel::RAKUMA, 9800, 588, 750, 1],
        ['Olympus OM-1', 9000, 40, 10, SalesChannel::MERCARI, 8500, 850, 850, 0],
    ];



    /**
     * サンプルデータを入れる
     *
     * @return void
     */
    public function run(): void
    {
        //
        $user = User::factory()->create([
            'family_name' => '古物',
            'given_name' => '太郎',
            'email' => 'demo@example.com',
            'office_name' => 'サンプル商店',
            'license_number' => '第301012345678号',
        ]);
        $this->seedRecords($user);
    }



    /**
     * 1ユーザー分の仕入（在庫中・売却済・下書き）を作る
     *
     * @param User $user
     * @return void
     */
    private function seedRecords(User $user): void
    {
        // 在庫中
        foreach (self::IN_STOCK_LIST as [$itemName, $features, $price, $daysAgo, $counterpartyIndex]) {
            Purchase::factory()->create(array_merge($this->counterparty($counterpartyIndex), [
                'user_id' => $user->id,
                'transaction_date' => now()->subDays($daysAgo)->toDateString(),
                'kubun' => Kubun::BUY,
                'item_name' => $itemName,
                'features' => $features,
                'price' => $price,
            ]));
        }

        // 売却済（台帳の払出行に出る）
        foreach (self::SOLD_LIST as [$itemName, $price, $boughtDaysAgo, $soldDaysAgo, $channel, $salePrice, $fee, $shippingCost, $counterpartyIndex]) {
            $purchase = Purchase::factory()->create(array_merge($this->counterparty($counterpartyIndex), [
                'user_id' => $user->id,
                'transaction_date' => now()->subDays($boughtDaysAgo)->toDateString(),
                'kubun' => Kubun::BUY,
                'item_name' => $itemName,
                'features' => null,
                'price' => $price,
            ]));
            Sale::factory()->create([
                'user_id' => $user->id,
                'purchase_id' => $purchase->id,
                'sale_date' => now()->subDays($soldDaysAgo)->toDateString(),
                'channel' => $channel,
                'sale_price' => $salePrice,
                'fee' => $fee,
                'shipping_cost' => $shippingCost,
            ]);
        }

        // 1万円未満の確認免除
        Purchase::factory()->exempt()->create([
            'user_id' => $user->id,
            'transaction_date' => now()->subDays(2)->toDateString(),
            'item_name' => '文庫本 まとめ売り 10冊',
            'features' => null,
            'price' => 800,
        ]);

        // 下書き（現場で金額だけ控えたもの）
        Purchase::factory()->draft()->create([
            'user_id' => $user->id,
            'transaction_date' => now()->toDateString(),
            'price' => 3000,
        ]);
    }



    /**
     * 相手方の項目を返す
     *
     * @param int $index
     * @return array
     */
    private function counterparty(int $index): array
    {
        [$name, $address, $occupation, $age, $verificationMethod] = self::COUNTERPARTY_LIST[$index];

        //
        return [
            'counterparty_name' => $name,
            'counterparty_address' => $address,
            'counterparty_occupation' => $occupation,
            'counterparty_age' => $age,
            'verification_method' => $verificationMethod,
        ];
    }
}
