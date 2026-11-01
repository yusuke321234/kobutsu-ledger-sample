<?php

namespace App\Http\Requests\Sale;

use App\Enums\SalesChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * 売却（払出）の更新リクエスト。
 * purchase_id（1仕入=1売却の紐づけ）は変更させないため受け取らない。
 */
class UpdateSaleRequest extends FormRequest
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

        //
        return [
            'sale_date' => ['required', 'date_format:Y-m-d'],
            'channel' => ['required', Rule::enum(SalesChannel::class)],
            'sale_price' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'fee' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'shipping_cost' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'counterparty_name' => ['nullable', 'string', 'max:255'],
            'counterparty_address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
