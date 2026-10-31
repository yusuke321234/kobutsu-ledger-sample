<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
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
            'family_name' => ['required', 'string', 'max:255'],
            'given_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
        ];
    }
}
