<?php

namespace App\Services;

use App\Contracts\Repositories\PurchaseRepositoryInterface;
use App\Contracts\Repositories\SaleRepositoryInterface;
use App\Contracts\Services\SaleServiceInterface;
use App\Support\DateText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 売却（払出）。1仕入＝1売却（ロットを分けて売る形は扱わない）。
 * 売却を記録すると、在庫は「売却済」になり、台帳の払出行も自動でできる（LedgerService が導出）。
 * 粗利は**単品の素朴粗利（売価−仕入）**を返す。
 *
 * 台帳の整合性のため、次の2つはここで守る（入力チェックだけに頼らない）：
 * - 1つの仕入に売却は1件まで（同時に2件の「売れた」が来ても、2件目は仕入の行のロックが解けるのを待ってから確かめる）
 * - 売却日は仕入日より前にできない
 */
class SaleService implements SaleServiceInterface
{
    private SaleRepositoryInterface $saleRepository;

    private PurchaseRepositoryInterface $purchaseRepository;

    /**
     * SaleRepositoryInterface / PurchaseRepositoryInterface を注入する
     *
     * @param SaleRepositoryInterface $saleRepository
     * @param PurchaseRepositoryInterface $purchaseRepository
     */
    public function __construct(SaleRepositoryInterface $saleRepository, PurchaseRepositoryInterface $purchaseRepository)
    {
        $this->saleRepository = $saleRepository;
        $this->purchaseRepository = $purchaseRepository;
    }



    /**
     * 売却を登録する（「売れた」）。
     *
     * FormRequest でも同じことを確かめているが、確かめてから書き込むまでの間に別のリクエストが割り込めるため、
     * 仕入の行をロックしてから確かめ直す。
     *
     * @param string $userId
     * @param array $registerData
     * @return array ['status' => 'ok' | 'not_sellable'（本人の仕入でない・下書き） | 'sold'（売却済み）
     *               | 'before_purchase_date'（売却日が仕入日より前）, 'data' => 作成した売却 | 仕入日]
     */
    public function register(string $userId, array $registerData): array
    {
        //
        $result = DB::transaction(function () use ($userId, $registerData): array {
            // 対象の仕入を行ロックして取り直す
            $purchaseId = (string)$registerData['purchase_id'];
            Log::info("売却する仕入を確認します。 purchase_id: {$purchaseId}, user_id: {$userId}");
            $purchaseData = $this->purchaseRepository->getDataForUpdate($purchaseId, $userId);
            if ($purchaseData !== null && (bool)$purchaseData['is_draft'] === false) {
                // ok.
            } else {
                Log::info("売却できる仕入ではありません。 purchase_id: {$purchaseId}");

                //
                return ['status' => 'not_sellable', 'data' => null];
            }

            // 1仕入=1売却（取り消した売却は数えない）
            if ($purchaseData['sale'] === null) {
                // ok.
            } else {
                Log::info("すでに売却済みのため登録しません。 purchase_id: {$purchaseId}");

                //
                return ['status' => 'sold', 'data' => null];
            }

            // 売却日は仕入日より前にできない
            $transactionDate = DateText::toDateString($purchaseData['transaction_date']);
            if (strcmp((string)$registerData['sale_date'], (string)$transactionDate) >= 0) {
                // ok.
            } else {
                Log::info("売却日が仕入日より前のため登録しません。 sale_date: {$registerData['sale_date']}, transaction_date: {$transactionDate}");

                //
                return ['status' => 'before_purchase_date', 'data' => ['transaction_date' => $transactionDate]];
            }

            //
            $saleId = $this->saleRepository->create($userId, $registerData);
            Log::info("売却を登録しました。 sale_id: {$saleId}");

            //
            $saleData = $this->saleRepository->getData($saleId, $userId);
            return ['status' => 'ok', 'data' => $this->shapeSale($saleData)];
        });

        //
        return $result;
    }



    /**
     * 売却を更新する（販路・売価・手数料・売却日の訂正）。
     *
     * @param string $userId
     * @param string $id
     * @param array $updateData
     * @return array ['status' => 'ok' | 'not_found'（本人の売却が無い） | 'before_purchase_date'（売却日が仕入日より前）,
     *               'data' => 更新後の売却 | 仕入日]
     */
    public function update(string $userId, string $id, array $updateData): array
    {
        //
        $result = DB::transaction(function () use ($userId, $id, $updateData): array {
            // 本人のものかを先に確認する
            Log::info("更新対象の売却を確認します。 sale_id: {$id}, user_id: {$userId}");
            $existing = $this->saleRepository->getData($id, $userId);
            if ($existing !== null) {
                Log::info("更新対象の売却が見つかりました。 sale_id: {$id}");
            } else {
                Log::info("更新対象の売却が見つかりませんでした。 sale_id: {$id}");

                //
                return ['status' => 'not_found', 'data' => null];
            }

            // 売却日は仕入日より前にできない（仕入日の変更と同時に来ても食い違わないよう、仕入の行をロックしてから比べる）
            $purchaseData = $this->purchaseRepository->getDataForUpdate((string)$existing['purchase_id'], $userId);
            $transactionDate = DateText::toDateString($purchaseData['transaction_date'] ?? null);
            if ($transactionDate === null || strcmp((string)$updateData['sale_date'], $transactionDate) >= 0) {
                // ok.
            } else {
                Log::info("売却日が仕入日より前のため更新しません。 sale_date: {$updateData['sale_date']}, transaction_date: {$transactionDate}");

                //
                return ['status' => 'before_purchase_date', 'data' => ['transaction_date' => $transactionDate]];
            }

            //
            $this->saleRepository->update($id, $userId, $updateData);
            Log::info("売却を更新しました。 sale_id: {$id}");

            //
            $saleData = $this->saleRepository->getData($id, $userId);
            return ['status' => 'ok', 'data' => $this->shapeSale($saleData)];
        });

        //
        return $result;
    }



    /**
     * 売却を論理削除する（売却の取り消し＝商品は在庫中に戻り、台帳の払出行も消える）。
     *
     * @param string $userId
     * @param string $id
     * @return bool
     */
    public function delete(string $userId, string $id): bool
    {
        //
        $deleted = $this->saleRepository->delete($id, $userId);
        Log::info("売却を論理削除しました。 sale_id: {$id}, 結果: " . ($deleted ? 'true' : 'false'));

        //
        return $deleted;
    }



    /**
     * 売却をレスポンス用の配列（snake_case）に整形する。
     * 区分コード（channel）は string、ID も string、金額は int（02-naming-conventions）。
     *
     * @param array $saleData
     * @return array
     */
    private function shapeSale(array $saleData): array
    {
        $purchaseData = $saleData['purchase'] ?? null;

        //
        $result = [
            'id' => (string)$saleData['id'],
            'purchase_id' => (string)$saleData['purchase_id'],
            'sale_date' => DateText::toDateString($saleData['sale_date'] ?? null),
            'channel' => (string)$saleData['channel'],
            'sale_price' => (int)$saleData['sale_price'],
            'fee' => (int)$saleData['fee'],
            'shipping_cost' => (int)$saleData['shipping_cost'],
            'counterparty_name' => $saleData['counterparty_name'],
            'counterparty_address' => $saleData['counterparty_address'],
            // 単品の素朴粗利（売価−仕入）
            'raw_gross_profit' => $this->rawGrossProfit($saleData, $purchaseData),
        ];
        return $result;
    }



    /**
     * 単品の素朴粗利（売価 − 仕入）。仕入金額が未入力なら null。
     *
     * @param array $saleData
     * @param array|null $purchaseData
     * @return int|null
     */
    private function rawGrossProfit(array $saleData, ?array $purchaseData): ?int
    {
        $profit = null;
        if ($purchaseData !== null && $purchaseData['price'] !== null) {
            $profit = (int)$saleData['sale_price'] - (int)$purchaseData['price'];
        } else {
            // no action.
        }

        //
        return $profit;
    }
}
