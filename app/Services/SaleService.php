<?php

namespace App\Services;

use App\Contracts\Repositories\SaleRepositoryInterface;
use App\Contracts\Services\SaleServiceInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * 売却（払出）。1仕入＝1売却（ロットを分けて売る形は扱わない）。
 * 売却を記録すると、在庫は「売却済」になり、台帳の払出行も自動でできる（LedgerService が導出）。
 *
 * 売却を記録すると、台帳の払出しの行が自動でできる（台帳は保存せず記録から組み立てるため）。
 * 粗利は**単品の素朴粗利（売価−仕入）**を返す。
 */
class SaleService implements SaleServiceInterface
{
    private SaleRepositoryInterface $saleRepository;

    /**
     * SaleRepositoryInterface を注入する
     *
     * @param SaleRepositoryInterface $saleRepository
     */
    public function __construct(SaleRepositoryInterface $saleRepository)
    {
        $this->saleRepository = $saleRepository;
    }



    /**
     * 売却を登録する（「売れた」）。
     * 対象の仕入が自分のもので・下書きでなく・未売却であることは FormRequest で担保済み。
     *
     * @param string $userId
     * @param array $registerData
     * @return array 作成した売却
     */
    public function register(string $userId, array $registerData): array
    {
        $saleId = $this->saleRepository->create($userId, $registerData);
        Log::info("売却を登録しました。 sale_id: {$saleId}");

        //
        $saleData = $this->saleRepository->getData($saleId, $userId);

        //
        $result = $this->shapeSale($saleData);
        return $result;
    }



    /**
     * 売却を更新する。本人のものでなければ null。
     *
     * @param string $userId
     * @param string $id
     * @param array $updateData
     * @return array|null 更新後の売却。対象が無ければ null
     */
    public function update(string $userId, string $id, array $updateData): ?array
    {
        // 本人のものかを先に確認する
        Log::info("更新対象の売却を確認します。 sale_id: {$id}, user_id: {$userId}");
        $existing = $this->saleRepository->getData($id, $userId);
        if ($existing === null) {
            Log::info("更新対象の売却が見つかりませんでした。 sale_id: {$id}");

            //
            return null;
        } else {
            Log::info("更新対象の売却が見つかりました。 sale_id: {$id}");
        }

        //
        $this->saleRepository->update($id, $userId, $updateData);
        Log::info("売却を更新しました。 sale_id: {$id}");

        //
        $saleData = $this->saleRepository->getData($id, $userId);
        $result = $this->shapeSale($saleData);
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
            'sale_date' => $this->toDateString($saleData['sale_date'] ?? null),
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



    /**
     * 日付文字列を 'Y-m-d' に整形する（null はそのまま null）
     *
     * @param string|null $value
     * @return string|null
     */
    private function toDateString(?string $value): ?string
    {
        $result = null;
        if ($value !== null) {
            $result = Carbon::parse($value)->toDateString();
        } else {
            // no action.
        }

        //
        return $result;
    }
}
