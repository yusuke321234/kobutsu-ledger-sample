<?php

namespace App\Repositories;

use App\Contracts\Repositories\PurchaseRepositoryInterface;
use App\Models\Purchase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class PurchaseRepository implements PurchaseRepositoryInterface
{
    private Purchase $purchase;

    /**
     * Purchase モデルを注入する
     *
     * @param Purchase $purchase
     */
    public function __construct(Purchase $purchase)
    {
        $this->purchase = $purchase;
    }



    /**
     * ユーザーの仕入一覧を取得する（取引日→id の降順、sale を eager load）
     *
     * @param string $userId
     * @param int $limit
     * @param int $offset
     * @param array $filterData status / q
     * @return array
     */
    public function getListByUserId(string $userId, int $limit, int $offset, array $filterData): array
    {
        //
        $list = $this->buildQuery($userId, $filterData)
            ->with(['sale', 'photos'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->toArray();

        //
        return $list;
    }



    /**
     * フィルタ条件に一致する仕入の総件数を取得する
     *
     * @param string $userId
     * @param array $filterData
     * @return int
     */
    public function countByUserId(string $userId, array $filterData): int
    {
        //
        $count = $this->buildQuery($userId, $filterData)->count();

        //
        return $count;
    }



    /**
     * id と user_id で仕入を取得する（本人のものでなければ null。sale を eager load）
     *
     * @param string $id
     * @param string $userId
     * @return array|null
     */
    public function getData(string $id, string $userId): ?array
    {
        //
        $purchase = $this->purchase
            ->with(['sale', 'photos'])
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        //
        return $purchase?->toArray();
    }



    /**
     * 仕入を作成する
     *
     * @param string $userId
     * @param array $createData
     * @return string 作成した仕入の id
     */
    public function create(string $userId, array $createData): string
    {
        Log::info("purchase を新規作成します。");

        //
        $createData['user_id'] = $userId;
        $result = $this->purchase->create($createData);

        //
        return (string)$result['id'];
    }



    /**
     * 仕入を更新する（本人の仕入だけ）
     *
     * @param string $id
     * @param string $userId
     * @param array $updateData
     * @return void
     */
    public function update(string $id, string $userId, array $updateData): void
    {
        Log::info("purchase (id: {$id}) を更新します。");

        //
        $this->purchase->where('id', $id)->where('user_id', $userId)->update($updateData);
    }



    /**
     * 仕入を論理削除する（法定3年保存のため物理削除しない。本人のもののみ）
     *
     * @param string $id
     * @param string $userId
     * @return bool 1件以上削除できたか
     */
    public function delete(string $id, string $userId): bool
    {
        //
        $deletedCount = $this->purchase->where('id', $id)->where('user_id', $userId)->delete();

        //
        return $deletedCount > 0;
    }



    /**
     * 相手方の履歴を重複排除して取得する（マスタ表を持たず purchases から。氏名で一意・新しい順）
     *
     * @param string $userId
     * @return array
     */
    public function getDistinctCounterparties(string $userId): array
    {
        //
        $list = $this->purchase
            ->where('user_id', $userId)
            ->whereNotNull('counterparty_name')
            ->where('counterparty_name', '!=', '')
            ->orderByDesc('id')
            ->get([
                'counterparty_name',
                'counterparty_address',
                'counterparty_occupation',
                'counterparty_age',
                'verification_method',
            ])
            ->unique('counterparty_name')
            ->values()
            ->toArray();

        //
        return $list;
    }



    /**
     * 台帳（受入）用に仕入を取得する。
     * 下書きは法定記録として未完成のため除外する（必須項目が未入力のことがある）。
     * ページングは Service 側で払出とマージしてから行うため、ここでは期間内を全件返す。
     *
     * @param string $userId
     * @param string|null $from 期間開始（Y-m-d）。null は下限なし
     * @param string|null $to 期間終了（Y-m-d）。null は上限なし
     * @param string $q 検索語（品目・特徴・相手方氏名）
     * @return array
     */
    public function getForLedger(string $userId, ?string $from, ?string $to, string $q): array
    {
        $query = $this->purchase->newQuery()->where('user_id', $userId)->where('is_draft', false);

        // 期間（取引年月日）。date キャストは 'Y-m-d H:i:s' で保存されるため素の文字列比較では
        // 終端日（to と同日）の記録が漏れる。日付部分で比較する whereDate を使う（ドライバ差も吸収）。
        if ($from !== null) {
            $query->whereDate('transaction_date', '>=', $from);
        } else {
            // no action.
        }
        if ($to !== null) {
            $query->whereDate('transaction_date', '<=', $to);
        } else {
            // no action.
        }

        // 検索
        if ($q !== '') {
            $query->where(function (Builder $sub) use ($q) {
                $sub->where('item_name', 'like', "%{$q}%")
                    ->orWhere('features', 'like', "%{$q}%")
                    ->orWhere('counterparty_name', 'like', "%{$q}%");
            });
        } else {
            // no action.
        }

        //
        $list = $query->orderByDesc('transaction_date')->orderByDesc('id')->get()->toArray();

        //
        return $list;
    }



    /**
     * user_id・status・q を適用したクエリビルダを組み立てる
     *
     * @param string $userId
     * @param array $filterData status / q
     * @return Builder
     */
    private function buildQuery(string $userId, array $filterData): Builder
    {
        $query = $this->purchase->newQuery()->where('user_id', $userId);

        // 状態フィルタ（在庫中＝下書きでなく未売却／売却済＝sale あり／下書き）
        $status = $filterData['status'] ?? 'all';
        if ($status === 'in_stock') {
            $query->where('is_draft', false)->whereDoesntHave('sale');
        } elseif ($status === 'sold') {
            $query->whereHas('sale');
        } elseif ($status === 'draft') {
            $query->where('is_draft', true);
        } else {
            // all: 状態での絞り込みなし
        }

        // 検索語（品目・特徴・相手方氏名の部分一致）
        $q = (string)($filterData['q'] ?? '');
        if ($q !== '') {
            $query->where(function (Builder $sub) use ($q) {
                $sub->where('item_name', 'like', "%{$q}%")
                    ->orWhere('features', 'like', "%{$q}%")
                    ->orWhere('counterparty_name', 'like', "%{$q}%");
            });
        } else {
            // no action.
        }

        return $query;
    }
}
