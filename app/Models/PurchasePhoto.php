<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 仕入の写真（1つの仕入につき5枚まで）。
 * 実体は Web 非公開の local ディスクに置き、path のみを持つ（配信は認証ルート経由）。
 */
#[Fillable([
    'user_id',
    'purchase_id',
    'path',
])]
class PurchasePhoto extends Model
{
    use HasFactory;

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }



    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
