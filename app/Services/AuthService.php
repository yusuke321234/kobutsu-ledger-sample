<?php

namespace App\Services;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Contracts\Services\AuthServiceInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class AuthService implements AuthServiceInterface
{
    private UserRepositoryInterface $userRepository;



    /**
     * UserRepositoryInterface を注入する
     *
     * @param UserRepositoryInterface $userRepository
     */
    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }



    /**
     * ユーザーを登録し、そのままログイン状態にする（メール確認なし）
     *
     * @param array $userData family_name / given_name / email / password
     * @return array 登録したユーザー情報
     */
    public function register(array $userData): array
    {
        // ユーザー登録（password は User の hashed キャストでハッシュ化される）
        $userId = $this->userRepository->create($userData);
        Log::info("ユーザーを登録しました。 user_id: {$userId}");

        // 登録後はそのまま id でログイン（セッション固定攻撃対策で ID を再生成）
        Auth::loginUsingId($userId);
        Session::regenerate();

        //
        $result = $this->getUserData($userId);
        return $result;
    }



    /**
     * メールアドレスとパスワードでログインする（web ガード = セッション）
     *
     * @param array $credentials email / password
     * @param bool $remember 自動ログイン（remember me）
     * @return array success（成否）と user_data（成功時のユーザー情報）
     */
    public function login(array $credentials, bool $remember): array
    {
        //
        $userData = [];
        $isAuthenticated = Auth::attempt($credentials, $remember);
        if ($isAuthenticated === true) {
            Log::info("ログインに成功しました。");

            // セッション固定攻撃対策で ID を再生成し、ユーザー情報を整形
            Session::regenerate();

            //
            $userData = $this->getUserData((string)Auth::id());
        } else {
            Log::info("ログインに失敗しました。 認証情報が一致しません。");
        }

        //
        $result = [
            'success' => $isAuthenticated,
            'user_data' => $userData,
        ];
        return $result;
    }



    /**
     * ログアウトする（セッションを破棄し CSRF トークンを再生成）
     *
     * @return void
     */
    public function logout(): void
    {
        //
        Auth::guard('web')->logout();
        Session::invalidate();
        Session::regenerateToken();
        Log::info("ログアウトしました。");
    }



    /**
     * id でユーザー情報を取得し、レスポンス用の配列（snake_case）に整形する
     *
     * @param string $userId
     * @return array
     */
    public function getUserData(string $userId): array
    {
        //
        $userData = $this->userRepository->getData($userId);

        // ID 系は string で返す（02-naming-conventions、Model の cast で string 化済み）
        $result = [
            'id' => $userData['id'],
            'family_name' => $userData['family_name'],
            'given_name' => $userData['given_name'],
            'email' => $userData['email'],
            // 様式15号の帳票ヘッダ用（未設定は null）
            'office_name' => $userData['office_name'] ?? null,
            'license_number' => $userData['license_number'] ?? null,
        ];
        return $result;
    }



    /**
     * ユーザーの氏名を変更する
     *
     * @param string $userId
     * @param array $nameData family_name / given_name
     * @return void
     */
    public function updateName(string $userId, array $nameData): void
    {
        //
        Log::info("氏名を変更します。 user_id: {$userId}");
        $this->userRepository->update($userId, $nameData);
        Log::info("氏名を変更しました。 user_id: {$userId}");
    }



    /**
     * ユーザーの営業所情報（様式15号の帳票ヘッダ用）を保存する
     *
     * @param string $userId
     * @param array $officeData office_name / license_number（未設定は null）
     * @return void
     */
    public function updateOffice(string $userId, array $officeData): void
    {
        //
        Log::info("営業所情報を保存します。 user_id: {$userId}");
        $this->userRepository->update($userId, $officeData);
        Log::info("営業所情報を保存しました。 user_id: {$userId}");
    }
}
