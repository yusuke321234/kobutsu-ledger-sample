<?php

namespace App\Repositories;

use App\Contracts\Repositories\SaleRepositoryInterface;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class SaleRepository implements SaleRepositoryInterface
{
    private Sale $sale;

    /**
     * Sale モデルを注入する
     *
     * @param Sale $sale
     */
    public function __construct(Sale $sale)
    {
        $this->sale = $sale;
    }



    /**
     * 台帳（払出）用に売却を取得する。品目・特徴は紐づく仕入から引くため purchase を eager load する。
     * ページングは Service 側で受入とマージしてから行うため、ここでは期間内を全件返す。
     *
     * @param string $userId
     * @param string|null $from 期間開始（Y-m-d）。null は下限なし
     * @param string|null $to 期間終了（Y-m-d）。null は上限なし
     * @param string $q 検索語（品目・特徴・相手方氏名）
     * @return array
     */
    public function getForLedger(string $userId, ?string $from, ?string $to, string $q): array
    {
        $query = $this->sale->newQuery()->with('purchase')->where('user_id', $userId);

        // 期間（売却年月日）。date キャストは 'Y-m-d H:i:s' で保存されるため素の文字列比較では
        // 終端日（to と同日）の記録が漏れる。日付部分で比較する whereDate を使う（ドライバ差も吸収）。
        if ($from !== null) {
            $query->whereDate('sale_date', '>=', $from);
        } else {
            // no action.
        }
        if ($to !== null) {
            $query->whereDate('sale_date', '<=', $to);
        } else {
            // no action.
        }

        // 検索（払出の品目・特徴は仕入側にあるため purchase を横断して探す）
        if ($q !== '') {
            $query->where(function (Builder $sub) use ($q) {
                $sub->where('counterparty_name', 'like', "%{$q}%")
                    ->orWhereHas('purchase', function (Builder $purchaseQuery) use ($q) {
                        $purchaseQuery->where('item_name', 'like', "%{$q}%")
                            ->orWhere('features', 'like', "%{$q}%");
                    });
            });
        } else {
            // no action.
        }

        //
        $list = $query->orderByDesc('sale_date')->orderByDesc('id')->get()->toArray();

        //
        return $list;
    }



    /**
     * id と user_id で売却を取得する（本人のものでなければ null。purchase を eager load）
     *
     * @param string $id
     * @param string $userId
     * @return array|null
     */
    public function getData(string $id, string $userId): ?array
    {
        //
        $sale = $this->sale
            ->with('purchase')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        //
        return $sale?->toArray();
    }



    /**
     * 売却を作成する
     *
     * @param string $userId
     * @param array $createData
     * @return string 作成した売却の id
     */
    public function create(string $userId, array $createData): string
    {
        Log::info("sale を新規作成します。");

        //
        $createData['user_id'] = $userId;
        $result = $this->sale->create($createData);

        //
        return (string)$result['id'];
    }



    /**
     * 売却を更新する（本人の売却だけ）
     *
     * @param string $id
     * @param string $userId
     * @param array $updateData
     * @return void
     */
    public function update(string $id, string $userId, array $updateData): void
    {
        Log::info("sale (id: {$id}) を更新します。");

        // purchase_id は 1仕入=1売却 の紐づけなので更新対象から外す
        unset($updateData['purchase_id']);

        //
        $this->sale->where('id', $id)->where('user_id', $userId)->update($updateData);
    }



    /**
     * 売却を論理削除する（法定3年保存のため物理削除しない。本人のもののみ）。
     * 削除すると台帳の払出行が消え、商品は在庫中に戻る（＝売却の取り消し）。
     *
     * @param string $id
     * @param string $userId
     * @return bool 1件以上削除できたか
     */
    public function delete(string $id, string $userId): bool
    {
        //
        $deletedCount = $this->sale->where('id', $id)->where('user_id', $userId)->delete();

        //
        return $deletedCount > 0;
    }
}
