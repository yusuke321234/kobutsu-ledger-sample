<?php

namespace App\Http\Requests\Ledger;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class GetLedgerListRequest extends FormRequest
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
            // 未指定は1ページ目（「もっと見る」で2ページ目以降を取りに来る）
            'current_page' => ['nullable', 'integer', 'min:1'],
            // page_size は過大なページサイズを防ぐため 100 を上限とする（未指定は PageConsts の既定）
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
            // 期間絞り込み（未指定は全期間）
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            // 検索語（品目・特徴・相手方氏名の部分一致）
            'q' => ['nullable', 'string', 'max:255'],
        ];
    }



    /**
     * バリデーション失敗時の戻り先（クエリを外した一覧へ戻す。不正なクエリのまま戻ると同じ失敗を繰り返すため）
     *
     * @return string
     */
    protected function getRedirectUrl()
    {
        //
        return $this->url();
    }
}
