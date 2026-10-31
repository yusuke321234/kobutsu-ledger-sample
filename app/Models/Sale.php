<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 売却（払出）。1仕入=1売却（ロットを分けて売る形は扱わない）。
 */
#[Fillable([
    'user_id',
    'purchase_id',
    'sale_date',
    'channel',
    'sale_price',
    'fee',
    'shipping_cost',
    'counterparty_name',
    'counterparty_address',
])]
class Sale extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * 型を変換する属性を返す
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
        ];
    }



    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }



    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }



    /**
     * 粗利（売価 − 仕入 − 手数料 − 送料）。仕入金額が未入力（下書き等）の場合は null。
     *
     * @return int|null
     */
    public function grossProfit(): ?int
    {
        $cost = $this->purchase?->price;

        if ($cost === null) {
            return null;
        }

        return $this->sale_price - $cost - $this->fee - $this->shipping_cost;
    }
}
