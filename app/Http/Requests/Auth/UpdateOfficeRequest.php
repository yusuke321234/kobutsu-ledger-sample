<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class UpdateOfficeRequest extends FormRequest
{
    /**
     * バリデーションルールを返す（営業所情報の保存。様式15号の帳票ヘッダ用）
     *
     * @return array
     */
    public function rules(): array
    {
        Log::info("validation start.");
        Log::info("parameterData: " . print_r($this->input(), true));

        // 未設定でも記録は成立するため任意。2項目をまとめて保存する（空は未設定に戻す）
        return [
            'office_name' => ['nullable', 'string', 'max:255'],
            'license_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}
