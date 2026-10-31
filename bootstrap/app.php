<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\InitialLog;
use App\Http\Middleware\LogValidationError;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 未ログインはログイン画面（ルート名 login）へ、ログイン済みでゲスト専用画面（ログイン・登録等）を開いたら
        // 在庫（ルート名 home）へ戻す。どちらも Laravel の既定の行き先なので redirectGuestsTo / redirectUsersTo は書かない
        //（公式スターターキットと同じ。行き先はルート名で決まるので、URL を変えてもここは直さなくてよい）

        // 画面のルート（routes/web.php）すべてに掛ける。
        // - InitialLog：リクエスト開始マーカー（IP / ホスト）
        // - LogValidationError：入力チェックに失敗した理由を記録する
        // - HandleInertiaRequests：全画面に共有する props（ログインユーザー）
        // - AddLinkHeadersForPreloadedAssets：ビルド済みの JS/CSS を先読みさせる Link ヘッダ
        $middleware->web(append: [
            InitialLog::class,
            LogValidationError::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // JSON を求める要求は JSON で返す。
        // 画面（Inertia）向けのエラー表示は AppServiceProvider::configureInertiaExceptions
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => $request->expectsJson(),
        );
    })->create();
