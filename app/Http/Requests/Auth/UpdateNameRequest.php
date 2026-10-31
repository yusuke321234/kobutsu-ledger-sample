<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class UpdateNameRequest extends FormRequest
{
    /**
     * バリデーションルールを返す（氏名の変更。メールアドレスの変更は対象外）
     *
     * @return array
     */
    public function rules(): array
    {
        Log::info("validation start.");
        Log::info("parameterData: " . print_r($this->input(), true));

        //
        return [
            'family_name' => ['required', 'string', 'max:255'],
            'given_name' => ['required', 'string', 'max:255'],
        ];
    }
}
