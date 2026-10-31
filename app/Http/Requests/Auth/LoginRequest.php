<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class LoginRequest extends FormRequest
{
    /**
     * バリデーションルールを返す
     *
     * @return array
     */
    public function rules(): array
    {
        Log::info("validation start.");
        // パスワードはログに出さない
        Log::info("parameterData: " . print_r($this->except(['password']), true));

        //
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }
}
