<?php

namespace App\Http\Requests\Ledger;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

/**
 * 台帳の出力（様式15号 PDF / CSV）リクエスト。
 * 出力は期間の全件が対象なのでページングは受け取らない。
 */
class GetLedgerExportRequest extends FormRequest
{
    /**
     * バリデーションルールを返す
     *
     * @return array
     */
    public function rules(): array
    {
        Log::info("validation start.");
        Log::info("parameterData: " . print_r($this->input(), true));

        //
        return [
            // 期間絞り込み（未指定は全期間）
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            // 検索語（品目・特徴・相手方氏名の部分一致）
            'q' => ['nullable', 'string', 'max:255'],
        ];
    }
}
