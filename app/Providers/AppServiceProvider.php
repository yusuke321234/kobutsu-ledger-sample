<?php

namespace App\Providers;

use App\Contracts\Repositories\PurchasePhotoRepositoryInterface;
use App\Contracts\Repositories\PurchaseRepositoryInterface;
use App\Contracts\Repositories\SaleRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Contracts\Services\AuthServiceInterface;
use App\Contracts\Services\LedgerServiceInterface;
use App\Contracts\Services\PurchasePhotoServiceInterface;
use App\Contracts\Services\PurchaseServiceInterface;
use App\Contracts\Services\SaleServiceInterface;
use App\Repositories\PurchasePhotoRepository;
use App\Repositories\PurchaseRepository;
use App\Repositories\SaleRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\LedgerService;
use App\Services\PurchasePhotoService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use App\Consts\ErrorConsts;
use App\Consts\FlashConsts;
use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\ExceptionResponse;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use App\Support\Toast;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ドメインの Service / Repository をインターフェースにバインド（DI）
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(PurchaseServiceInterface::class, PurchaseService::class);
        $this->app->bind(PurchaseRepositoryInterface::class, PurchaseRepository::class);
        $this->app->bind(PurchasePhotoServiceInterface::class, PurchasePhotoService::class);
        $this->app->bind(PurchasePhotoRepositoryInterface::class, PurchasePhotoRepository::class);
        $this->app->bind(SaleServiceInterface::class, SaleService::class);
        $this->app->bind(SaleRepositoryInterface::class, SaleRepository::class);
        $this->app->bind(LedgerServiceInterface::class, LedgerService::class);
    }



    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiters();
        $this->configureInertiaExceptions();
    }



    /**
     * アプリ全体の既定値を決める（公式スターターキットの configureDefaults と同じ）
     *
     * - 日付は書き換えできない型（CarbonImmutable）にする。$end = $start; $end->addDay(); で
     *   元の $start まで変わってしまう種類の不具合を防ぐ（now()・Model の日付の列が対象）
     * - 本番では DB を消すコマンド（migrate:fresh / migrate:refresh / migrate:reset /
     *   migrate:rollback / db:wipe）を実行できなくする。台帳は法定3年保存・論理削除のみなので、
     *   うっかり消すと取り返しがつかない。デプロイの migrate --force は対象外
     *
     * @return void
     */
    private function configureDefaults(): void
    {
        //
        Date::use(CarbonImmutable::class);

        //
        DB::prohibitDestructiveCommands(app()->isProduction());
    }



    /**
     * 認証系エンドポイントのレート制限（スロットリング）を定義する
     *
     * ログインは「メールアドレス+IP」で 5回/分（1アカウントへの総当たり対策）に加え、
     * 「IP」で 20回/分（メールアドレスを変えて試すクレデンシャルスタッフィング対策）を掛ける。
     * 登録は IP ごとに 5回/分。制限名は routes/web.php の throttle:{名前} と対応する。
     *
     * @return void
     */
    private function configureRateLimiters(): void
    {
        // 制限に掛かったときのレスポンス（日本語メッセージ。Retry-After 等のヘッダはそのまま返す）。
        // 画面（Inertia）にはフォームのメールアドレス欄のエラーとして返す（ログイン・登録のどちらにもある項目）
        $tooManyAttempts = function (Request $request, array $headers): Response {
            $message = 'お試し回数が上限に達しました。しばらく時間をおいてからもう一度お試しください。';

            //
            if ($request->expectsJson() === true) {
                Log::info("レート制限に達したため 429 を返します。");

                //
                return response()->json(['message' => $message], 429, $headers);
            } else {
                Log::info("レート制限に達したため入力画面へ戻します。");

                //
                return back()->withErrors(['email' => $message])->withHeaders($headers);
            }
        };

        // ログイン（成功・失敗を問わず数える）
        RateLimiter::for("login", function (Request $request) use ($tooManyAttempts) {
            // get（大文字小文字を変えて上限を回避されないよう小文字で正規化する）
            $email = mb_strtolower((string)$request->input('email'));
            $ip = (string)$request->ip();

            //
            return [
                Limit::perMinute(5)->by("login:{$email}|{$ip}")->response($tooManyAttempts),
                Limit::perMinute(20)->by("login-ip:{$ip}")->response($tooManyAttempts),
            ];
        });

        // 新規登録（同一 IP からの大量登録を抑える）
        RateLimiter::for("register", function (Request $request) use ($tooManyAttempts) {
            // get
            $ip = (string)$request->ip();

            //
            return Limit::perMinute(5)->by("register:{$ip}")->response($tooManyAttempts);
        });
    }



    /**
     * 画面（Inertia）で起きたエラーの見せ方を決める。
     * 素の HTML エラーページを XHR で受けると Inertia がモーダルに出してしまうため、
     * 画面遷移（GET）はエラー画面、操作（POST/PUT/DELETE）は元の画面へ戻してトーストで伝える。
     * 操作の失敗はエラー（ErrorConsts::REQUEST）も載せて返す＝画面側は onError になり、
     * 成功時の処理（モーダルを閉じる・連続登録の入力を空にする等）が走らない。
     *
     * @return void
     */
    private function configureInertiaExceptions(): void
    {
        Inertia::handleExceptionsUsing(function (ExceptionResponse $response) {
            $request = $response->request;
            $statusCode = $response->statusCode();
            $isInertia = $request->header('X-Inertia') !== null;

            // JSON を求める要求は既定のまま返す
            if ($request->expectsJson() === true) {
                return null;
            } elseif ($response->exception instanceof AuthenticationException) {
                // 初回表示の誘導は既定のリダイレクトのまま
                if ($isInertia === true) {
                    // ok.
                } else {
                    return null;
                }

                // 画面を開いたままセッションが切れた場合は、理由を伝えてログイン画面をページごと開き直す
                //（途中だった操作は成功扱いにしない＝画面側の完了処理を走らせない）
                Log::info("セッション切れのためログイン画面へ移します。");
                if ($request->isMethod('GET') === true) {
                    $request->session()->put('url.intended', $request->fullUrl());
                } else {
                    // no action.
                }
                Inertia::flash(FlashConsts::TOAST_DATA, Toast::error('再度ログインしてください。'));
                return Inertia::location(route('login'));
            } elseif ($statusCode === 419) {
                Log::info("CSRF トークンの期限切れのため元の画面へ戻します。");

                //
                $message = 'ページの有効期限が切れました。もう一度お試しください。';
                Inertia::flash(FlashConsts::TOAST_DATA, Toast::error($message));
                return back()->withErrors([ErrorConsts::REQUEST => $message]);
            } elseif ($isInertia === true && $request->isMethod('GET') === false && in_array($statusCode, [403, 404, 500, 503], true) === true) {
                Log::info("操作が失敗したため元の画面へ戻します。 status: {$statusCode}");

                //
                $message = $this->errorMessage($response->exception, $statusCode);
                Inertia::flash(FlashConsts::TOAST_DATA, Toast::error($message));
                return back()->withErrors([ErrorConsts::REQUEST => $message]);
            } elseif ($request->isMethod('GET') === true && in_array($statusCode, [403, 404], true) === true) {
                return $response->render('error', ['status' => $statusCode])->withSharedData();
            } elseif ($request->isMethod('GET') === true && in_array($statusCode, [500, 503], true) === true && config('app.debug') === false) {
                // 開発中（APP_DEBUG=true）は Laravel の詳細なエラー画面をそのまま出す
                return $response->render('error', ['status' => $statusCode])->withSharedData();
            } else {
                return null;
            }
        });
    }



    /**
     * 操作が失敗したときにトーストで伝える文言を返す（abort() の文言は利用者向けに書いているのでそのまま使う）
     *
     * @param \Throwable $exception
     * @param int $statusCode
     * @return string
     */
    private function errorMessage(\Throwable $exception, int $statusCode): string
    {
        $message = '通信に失敗しました。時間をおいて再度お試しください。';
        if ($exception instanceof HttpExceptionInterface && $statusCode < 500 && $exception->getMessage() !== '') {
            $message = $exception->getMessage();
        } elseif ($statusCode === 403) {
            $message = 'この操作は実行できません。';
        } elseif ($statusCode === 404) {
            $message = '対象が見つかりません。';
        } else {
            // no action.（5xx は詳細を出さない）
        }

        //
        return $message;
    }
}
