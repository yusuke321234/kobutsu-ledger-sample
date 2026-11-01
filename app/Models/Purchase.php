<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 仕入（受入）。様式15号の台帳行の元データであり、税務（仕入原価）の元データでもある。
 * 在庫中/売却済のステータスは列を持たず、sale の有無から導出する。
 */
#[Fillable([
    'user_id',
    'transaction_date',
    'kubun',
    'item_name',
    'quantity',
    'features',
    'counterparty_name',
    'counterparty_address',
    'counterparty_occupation',
    'counterparty_age',
    'verification_method',
    'price',
    'is_exempt',
    'is_draft',
])]
class Purchase extends Model
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
            'transaction_date' => 'date',
            'is_exempt' => 'boolean',
            'is_draft' => 'boolean',
        ];
    }



    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }



    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class);
    }



    public function photos(): HasMany
    {
        return $this->hasMany(PurchasePhoto::class);
    }
}
