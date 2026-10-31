<?php

namespace App\Services;

use App\Contracts\Repositories\PurchaseRepositoryInterface;
use App\Contracts\Services\PurchaseServiceInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class PurchaseService implements PurchaseServiceInterface
{
    private PurchaseRepositoryInterface $purchaseRepository;

    /**
     * PurchaseRepositoryInterface を注入する
     *
     * @param PurchaseRepositoryInterface $purchaseRepository
     */
    public function __construct(PurchaseRepositoryInterface $purchaseRepository)
    {
        $this->purchaseRepository = $purchaseRepository;
    }



    /**
     * ユーザーの仕入一覧を取得する（検索・状態フィルタ・ページネーション）
     *
     * @param string $userId
     * @param int $currentPage 1始まりのページ番号
     * @param int $pageSize 1ページの件数
     * @param array $filterData status / q
     * @return array
     */
    public function getList(string $userId, int $currentPage, int $pageSize, array $filterData): array
    {
        $offset = ($currentPage - 1) * $pageSize;

        //
        $purchaseList = $this->purchaseRepository->getListByUserId($userId, $pageSize, $offset, $filterData);
        $totalCount = $this->purchaseRepository->countByUserId($userId, $filterData);
        Log::info("仕入一覧を取得しました。 user_id: {$userId}, 取得件数: " . count($purchaseList) . ", 総件数: {$totalCount}");

        //
        $list = [];
        foreach ($purchaseList as $purchaseData) {
            $list[] = $this->shapePurchase($purchaseData);
        }

        //
        $result = [
            'total_count' => $totalCount,
            'list' => $list,
        ];
        return $result;
    }



    /**
     * 仕入が1件でもあるかを返す（絞り込み・検索なし）
     *
     * 在庫画面で一覧が0件のとき、「まだ登録がない」のか「絞り込み・検索に当てはまらない」のかを出し分けるため。
     *
     * @param string $userId
     * @return bool
     */
    public function hasAny(string $userId): bool
    {
        //
        $count = $this->purchaseRepository->countByUserId($userId, ['status' => 'all', 'q' => '']);
        Log::info("仕入の有無を確認しました。 user_id: {$userId}, 件数: {$count}");

        //
        if ($count > 0) {
            return true;
        } else {
            return false;
        }
    }



    /**
     * 仕入を登録する（連続登録の1件。作成後に整形して返す）
     *
     * @param string $userId
     * @param array $registerData
     * @return array 作成した仕入
     */
    public function register(string $userId, array $registerData): array
    {
        $purchaseId = $this->purchaseRepository->create($userId, $registerData);
        Log::info("仕入を登録しました。 purchase_id: {$purchaseId}");

        //
        $purchaseData = $this->purchaseRepository->getData($purchaseId, $userId);

        //
        $result = $this->shapePurchase($purchaseData);
        return $result;
    }



    /**
     * 仕入を更新する（インライン編集・下書きの完成）。本人のものでなければ null。
     *
     * @param string $userId
     * @param string $id
     * @param array $updateData
     * @return array|null 更新後の仕入。対象が無ければ null
     */
    public function update(string $userId, string $id, array $updateData): ?array
    {
        // 本人のものかを先に確認する
        Log::info("更新対象の仕入を確認します。 purchase_id: {$id}, user_id: {$userId}");
        $existing = $this->purchaseRepository->getData($id, $userId);
        if ($existing === null) {
            Log::info("更新対象の仕入が見つかりませんでした。 purchase_id: {$id}");

            //
            return null;
        } else {
            Log::info("更新対象の仕入が見つかりました。 purchase_id: {$id}");
        }

        //
        $this->purchaseRepository->update($id, $userId, $updateData);
        Log::info("仕入を更新しました。 purchase_id: {$id}");

        //
        $purchaseData = $this->purchaseRepository->getData($id, $userId);
        $result = $this->shapePurchase($purchaseData);
        return $result;
    }



    /**
     * 仕入を論理削除する。売却済みは削除しない（先に売却取消が必要）。
     *
     * 売却済みの仕入を消すと、台帳に払出しの行だけが品目の空いたまま残ってしまうため。
     *
     * @param string $userId
     * @param string $id
     * @return array ['status' => 'ok' | 'not_found'（本人の仕入が無い） | 'sold'（売却済み）]
     */
    public function delete(string $userId, string $id): array
    {
        // 本人のものかを先に確認する
        Log::info("削除対象の仕入を確認します。 purchase_id: {$id}, user_id: {$userId}");
        $purchaseData = $this->purchaseRepository->getData($id, $userId);
        if ($purchaseData !== null) {
            // ok.
        } else {
            Log::info("削除対象の仕入が見つかりませんでした。 purchase_id: {$id}");

            //
            $result = ['status' => 'not_found'];
            return $result;
        }

        // 売却済みは削除しない（売却取消すると sale は空になる）
        if ($purchaseData['sale'] === null) {
            // ok.
        } else {
            Log::info("売却済みのため削除しません。 purchase_id: {$id}");

            //
            $result = ['status' => 'sold'];
            return $result;
        }

        //
        $this->purchaseRepository->delete($id, $userId);
        Log::info("仕入を論理削除しました。 purchase_id: {$id}");

        //
        $result = ['status' => 'ok'];
        return $result;
    }



    /**
     * 相手方チップ用の履歴（重複排除済み）を取得する
     *
     * @param string $userId
     * @return array
     */
    public function getCounterparties(string $userId): array
    {
        $counterpartyList = $this->purchaseRepository->getDistinctCounterparties($userId);
        Log::info("相手方履歴を取得しました。 user_id: {$userId}, 件数: " . count($counterpartyList));

        //
        $list = [];
        foreach ($counterpartyList as $counterparty) {
            $list[] = $this->shapeCounterparty($counterparty);
        }

        //
        $result = [
            'list' => $list,
        ];
        return $result;
    }



    /**
     * 仕入の行をレスポンス用の配列（snake_case）に整形する。
     * 区分コード（kubun / verification_method）は string、ID も string、金額・数量は int（02-naming-conventions）。
     *
     * @param array $purchaseData
     * @return array
     */
    private function shapePurchase(array $purchaseData): array
    {
        $saleData = $purchaseData['sale'] ?? null;

        // 確認方法（区分コード）は string、金額は int にそろえる（未入力は null のまま）
        $verificationMethod = null;
        if ($purchaseData['verification_method'] !== null) {
            $verificationMethod = (string)$purchaseData['verification_method'];
        } else {
            // no action.
        }
        $price = null;
        if ($purchaseData['price'] !== null) {
            $price = (int)$purchaseData['price'];
        } else {
            // no action.
        }

        // 売却済みなら売却の要約を付ける
        $saleSummaryData = null;
        if ($saleData !== null) {
            $saleSummaryData = $this->shapeSaleSummary($saleData);
        } else {
            // no action.
        }

        //
        $result = [
            'id' => (string)$purchaseData['id'],
            'transaction_date' => $this->toDateString($purchaseData['transaction_date'] ?? null),
            'kubun' => (string)$purchaseData['kubun'],
            'item_name' => $purchaseData['item_name'],
            'quantity' => (int)$purchaseData['quantity'],
            'features' => $purchaseData['features'],
            'counterparty_name' => $purchaseData['counterparty_name'],
            'counterparty_address' => $purchaseData['counterparty_address'],
            'counterparty_occupation' => $purchaseData['counterparty_occupation'],
            'counterparty_age' => $purchaseData['counterparty_age'],
            'verification_method' => $verificationMethod,
            'price' => $price,
            'is_exempt' => (bool)$purchaseData['is_exempt'],
            // 写真（1つの仕入につき5枚まで）。実体は Web 非公開なので認証ルートの URL を返す
            'photo_list' => $this->shapePhotoList($purchaseData['photos'] ?? []),
            'is_draft' => (bool)$purchaseData['is_draft'],
            // 状態は sale の有無・is_draft から導出（カラムを持たない）
            'status' => $this->deriveStatus($purchaseData, $saleData),
            'sale_data' => $saleSummaryData,
            // 単品の素朴粗利（売価−仕入）
            'raw_gross_profit' => $this->rawGrossProfit($purchaseData, $saleData),
            'created_at' => $this->toDateTimeString($purchaseData['created_at'] ?? null),
        ];
        return $result;
    }



    /**
     * 写真をレスポンス用に整形する。
     * 実体は Web 非公開の local ディスクにあるため、所有者チェック付きの配信ルートの URL を返す。
     *
     * @param array $photoList
     * @return array
     */
    private function shapePhotoList(array $photoList): array
    {
        $result = [];
        foreach ($photoList as $photoData) {
            $result[] = [
                'id' => (string)$photoData['id'],
                // 認証付きの配信ルート。画面へは相対 URL（/photos/{id}）で渡す
                'url' => route('photos.show', ['id' => $photoData['id']], false),
            ];
        }

        //
        return $result;
    }



    /**
     * 相手方履歴の1件をレスポンス用に整形する（チップ用）
     *
     * @param array $counterparty
     * @return array
     */
    private function shapeCounterparty(array $counterparty): array
    {
        // 確認方法（区分コード）は string にそろえる（未入力は null のまま）
        $verificationMethod = null;
        if ($counterparty['verification_method'] !== null) {
            $verificationMethod = (string)$counterparty['verification_method'];
        } else {
            // no action.
        }

        //
        $result = [
            'counterparty_name' => $counterparty['counterparty_name'],
            'counterparty_address' => $counterparty['counterparty_address'],
            'counterparty_occupation' => $counterparty['counterparty_occupation'],
            'counterparty_age' => $counterparty['counterparty_age'],
            'verification_method' => $verificationMethod,
        ];
        return $result;
    }



    /**
     * 商品ステータスを導出する（draft / sold / in_stock）
     *
     * @param array $purchaseData
     * @param array|null $saleData
     * @return string
     */
    private function deriveStatus(array $purchaseData, ?array $saleData): string
    {
        $status = 'in_stock';
        if ((bool)$purchaseData['is_draft'] === true) {
            $status = 'draft';
        } elseif ($saleData !== null) {
            $status = 'sold';
        } else {
            // in_stock のまま
        }

        //
        return $status;
    }



    /**
     * 単品の素朴粗利（売価 − 仕入）。売却がない/仕入未入力のときは null。
     *
     * @param array $purchaseData
     * @param array|null $saleData
     * @return int|null
     */
    private function rawGrossProfit(array $purchaseData, ?array $saleData): ?int
    {
        $profit = null;
        if ($saleData !== null && $purchaseData['price'] !== null) {
            $profit = (int)$saleData['sale_price'] - (int)$purchaseData['price'];
        } else {
            // no action.
        }

        //
        return $profit;
    }



    /**
     * 売却の要約（一覧カード表示用の最小項目）を整形する
     *
     * @param array $saleData
     * @return array
     */
    private function shapeSaleSummary(array $saleData): array
    {
        //
        $result = [
            'id' => (string)$saleData['id'],
            'sale_date' => $this->toDateString($saleData['sale_date'] ?? null),
            'channel' => (string)$saleData['channel'],
            'sale_price' => (int)$saleData['sale_price'],
        ];
        return $result;
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



    /**
     * 日時文字列を 'Y-m-d H:i:s' に整形する（null はそのまま null）
     *
     * @param string|null $value
     * @return string|null
     */
    private function toDateTimeString(?string $value): ?string
    {
        $result = null;
        if ($value !== null) {
            $result = Carbon::parse($value)->toDateTimeString();
        } else {
            // no action.
        }

        //
        return $result;
    }
}
