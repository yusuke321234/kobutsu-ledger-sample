<?php

namespace App\Http\Requests\Sale;

use App\Enums\SalesChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * 売却（払出）の登録リクエスト。
 * 「1仕入＝1売却」「他人の仕入は売れない」「下書きは売れない（記録として未完成）」を先に弾き、画面に理由を出す。
 * 同時に来たリクエストで割り込まれる場合や、仕入日との前後は SaleService が仕入の行をロックしてから確かめ直す。
 */
class RegisterSaleRequest extends FormRequest
{
    /**
     * バリデーションルールを返す
     *
     * @return array
     */
    public function rules(): array
    {
        Log::info("validation start.");
        // 相手方の住所はログに出さない
        Log::info("parameterData: " . print_r($this->except(['counterparty_address']), true));

        $userId = $this->user()->id;

        //
        return [
            'purchase_id' => [
                'required',
                // 自分の・削除されていない・下書きでない仕入だけを売却できる
                Rule::exists('purchases', 'id')->where(function ($query) use ($userId) {
                    $query->where('user_id', $userId)
                        ->where('is_draft', false)
                        ->whereNull('deleted_at');
                }),
                // 1仕入=1売却（論理削除された売却は除外＝売却を取り消せば再度売れる）
                Rule::unique('sales', 'purchase_id')->whereNull('deleted_at'),
            ],
            'sale_date' => ['required', 'date'],
            'channel' => ['required', Rule::enum(SalesChannel::class)],
            'sale_price' => ['required', 'integer', 'min:0', 'max:1000000000'],
            // 商品に紐づく経費のみ（一般経費は会計ソフトの領分なので扱わない）
            'fee' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'shipping_cost' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            // 払出の相手方（記載義務がある取引用・任意）
            'counterparty_name' => ['nullable', 'string', 'max:255'],
            'counterparty_address' => ['nullable', 'string', 'max:255'],
        ];
    }



    /**
     * バリデーションの日本語メッセージを返す
     *
     * @return array
     */
    public function messages(): array
    {
        //
        return [
            'purchase_id.exists' => 'この商品は売却できません（見つからないか、下書きのままです）。',
            'purchase_id.unique' => 'この商品はすでに売却済みです（1つの仕入に対し売却は1件までです）。',
        ];
    }
}
