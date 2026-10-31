<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpFoundation\Response;

/**
 * 入力チェックに失敗した理由をログに残す
 *
 * FormRequest は失敗すると、エラーをセッションに載せて元の画面へリダイレクトする（Laravel 標準）。
 * その戻り（リダイレクト）を見て、ここで1回だけ記録する。
 * FormRequest 側には書かない（17個の FormRequest に同じコードを置かないため）。
 */
class LogValidationError
{
    /**
     * リダイレクトでエラーを返したときに、その内容をログする
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 入力チェックの失敗はリダイレクト（画面へ戻す）。リダイレクト以外は対象外
        if ($response->isRedirect() === true) {
            // ok.
        } else {
            return $response;
        }

        //
        $errorList = $this->getErrorList($request);
        if ($errorList !== []) {
            Log::info("validation error. errors: " . print_r($errorList, true));
        } else {
            // no action.
        }

        //
        return $response;
    }



    /**
     * セッションに載せられたエラー（項目名 => メッセージ）を返す
     *
     * @param Request $request
     * @return array
     */
    private function getErrorList(Request $request): array
    {
        if ($request->hasSession() === true) {
            // ok.
        } else {
            return [];
        }

        //
        $errors = $request->session()->get('errors');
        if ($errors instanceof ViewErrorBag) {
            return $errors->getBag('default')->messages();
        } else {
            return [];
        }
    }
}
