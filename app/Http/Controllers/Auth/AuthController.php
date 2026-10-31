<?php

namespace App\Http\Controllers\Auth;

use App\Consts\FlashConsts;
use App\Contracts\Services\AuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateNameRequest;
use App\Http\Requests\Auth\UpdateOfficeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use App\Support\Toast;

class AuthController extends Controller
{
    private AuthServiceInterface $authService;



    /**
     * AuthServiceInterface を注入する
     *
     * @param AuthServiceInterface $authService
     */
    public function __construct(AuthServiceInterface $authService)
    {
        $this->authService = $authService;
    }



    /**
     * ログイン画面を表示する
     *
     * @param Request $request
     * @return Response
     */
    public function showLogin(Request $request): Response
    {
        // get
        //

        // log
        Log::info("ログイン画面を表示します。");

        // execute
        //

        // return
        return Inertia::render('auth/login');
    }



    /**
     * ログイン（成功したら、ログイン前に開こうとした画面へ戻す）
     *
     * @param LoginRequest $request
     * @return RedirectResponse
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        // get
        $credentials = [
            'email' => (string)$request->validated('email'),
            'password' => (string)$request->validated('password'),
        ];
        $remember = (bool)$request->safe()->boolean('remember');

        // log（パスワードはマスクして配列ダンプ）
        $logData = $credentials;
        $logData['password'] = '********';
        Log::info("credentials: " . print_r($logData, true));
        Log::info("remember: " . var_export($remember, true));

        // execute
        $result = $this->authService->login($credentials, $remember);

        // return
        if ($result['success'] === true) {
            // ok.
        } else {
            Log::info("ログイン失敗のためログイン画面へ戻します。");

            //
            return back()->withErrors([
                'email' => 'メールアドレスまたはパスワードが違います。',
            ]);
        }
        return redirect()->intended(route('home'));
    }



    /**
     * 新規登録画面を表示する
     *
     * @param Request $request
     * @return Response
     */
    public function showRegister(Request $request): Response
    {
        // get
        //

        // log
        Log::info("新規登録画面を表示します。");

        // execute
        //

        // return
        return Inertia::render('auth/register');
    }



    /**
     * ユーザー登録（登録後そのままログイン状態にして在庫へ）
     *
     * @param RegisterRequest $request
     * @return RedirectResponse
     */
    public function register(RegisterRequest $request): RedirectResponse
    {
        // get
        $userData = [
            'family_name' => (string)$request->validated('family_name'),
            'given_name' => (string)$request->validated('given_name'),
            'email' => (string)$request->validated('email'),
            'password' => (string)$request->validated('password'),
        ];

        // log（パスワードはマスクして配列ダンプ）
        $logData = $userData;
        $logData['password'] = '********';
        Log::info("userData: " . print_r($logData, true));

        // execute
        $this->authService->register($userData);

        // return
        return redirect()->route('home');
    }



    /**
     * ログアウト
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function logout(Request $request): RedirectResponse
    {
        // get
        //

        // log
        Log::info("ログアウト処理を実行します。");

        // execute
        $this->authService->logout();

        // return
        return redirect()->route('login');
    }



    /**
     * 設定画面を表示する（氏名・営業所情報は共有 props の auth から表示する）
     *
     * @param Request $request
     * @return Response
     */
    public function showSettings(Request $request): Response
    {
        // get
        $userId = (string)$request->user()->id;

        // log
        Log::info("user_id: {$userId}");

        // execute
        //

        // return
        return Inertia::render('settings');
    }



    /**
     * ログイン中ユーザーの氏名を変更する（メールアドレスの変更は対象外）
     *
     * @param UpdateNameRequest $request
     * @return RedirectResponse
     */
    public function updateName(UpdateNameRequest $request): RedirectResponse
    {
        // get
        $userId = (string)$request->user()->id;
        $nameData = [
            'family_name' => (string)$request->validated('family_name'),
            'given_name' => (string)$request->validated('given_name'),
        ];

        // log
        Log::info("user_id: {$userId}");
        Log::info("nameData: " . print_r($nameData, true));

        // execute
        $this->authService->updateName($userId, $nameData);

        // return
        Inertia::flash(FlashConsts::TOAST_DATA, Toast::success('氏名を変更しました。'));
        return back();
    }



    /**
     * ログイン中ユーザーの営業所情報（様式15号の帳票ヘッダ用）を保存する
     *
     * @param UpdateOfficeRequest $request
     * @return RedirectResponse
     */
    public function updateOffice(UpdateOfficeRequest $request): RedirectResponse
    {
        // get（空は未設定＝null に寄せる）
        $userId = (string)$request->user()->id;
        $officeData = [
            'office_name' => $this->toNullableText($request->validated('office_name')),
            'license_number' => $this->toNullableText($request->validated('license_number')),
        ];

        // log
        Log::info("user_id: {$userId}");
        Log::info("officeData: " . print_r($officeData, true));

        // execute
        $this->authService->updateOffice($userId, $officeData);

        // return
        Inertia::flash(FlashConsts::TOAST_DATA, Toast::success('営業所情報を保存しました。'));
        return back();
    }



    /**
     * 任意入力の文字列を、空なら null（未設定）に寄せる
     *
     * @param mixed $value
     * @return string|null
     */
    private function toNullableText(mixed $value): ?string
    {
        $text = (string)$value;
        if ($text !== '') {
            return $text;
        } else {
            return null;
        }
    }
}
