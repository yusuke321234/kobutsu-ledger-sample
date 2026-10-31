<?php

namespace App\Http\Requests\Purchase;

use App\Enums\Kubun;
use App\Enums\VerificationMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * 仕入（受入）の登録リクエスト。
 * is_draft=true（下書き＝現場捕獲）は緩い検証、is_draft=false（確定）は様式15号の必須項目を要求する。
 * 相手方4項目は「確認免除（1万円未満等）」の場合は確定でも任意。
 */
class RegisterPurchaseRequest extends FormRequest
{
    /**
     * バリデーションルールを返す
     *
     * @return array
     */
    public function rules(): array
    {
        Log::info("validation start.");
        // 相手方の個人情報（氏名以外）と写真の本体はログに出さない（input() にファイルは含まれない）
        Log::info("parameterData: " . print_r($this->except(['counterparty_address', 'counterparty_occupation', 'counterparty_age']), true));

        // 確定（下書きでない）＝様式15号の必須項目を要求する
        $isConfirmed = $this->boolean('is_draft') === false;
        // 確認免除（EXEMPT）または is_exempt のときは相手方の記帳が任意になる
        $verificationMethod = (string)$this->input('verification_method');
        $requiresCounterparty = $isConfirmed
            && $this->boolean('is_exempt') === false
            && $verificationMethod !== VerificationMethod::EXEMPT->value;

        //
        return [
            'transaction_date' => ['required', 'date'],
            'kubun' => ['required', Rule::enum(Kubun::class)],
            'item_name' => [Rule::requiredIf($isConfirmed), 'nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'features' => ['nullable', 'string', 'max:2000'],
            'verification_method' => [Rule::requiredIf($isConfirmed), 'nullable', Rule::enum(VerificationMethod::class)],
            'price' => [Rule::requiredIf($isConfirmed), 'nullable', 'integer', 'min:0', 'max:1000000000'],
            'counterparty_name' => [Rule::requiredIf($requiresCounterparty), 'nullable', 'string', 'max:255'],
            'counterparty_address' => ['nullable', 'string', 'max:255'],
            'counterparty_occupation' => ['nullable', 'string', 'max:255'],
            'counterparty_age' => ['nullable', 'string', 'max:50'],
            'is_exempt' => ['nullable', 'boolean'],
            'is_draft' => ['nullable', 'boolean'],
            // 写真（任意。枚数の上限は保存時に Service で判定する）
            'photo' => [
                'nullable',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp,image/heic,image/heif',
                // スマホ写真の実サイズを考慮しつつ、保存コストが膨らまない上限（8MB）
                'max:8192',
            ],
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
            'photo.mimetypes' => '画像ファイル（JPEG・PNG・WebP・HEIC）を選んでください。',
            'photo.max' => '写真は8MBまでです。',
        ];
    }
}
