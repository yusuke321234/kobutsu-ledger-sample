<?php

namespace App\Http\Middleware;

use App\Contracts\Services\AuthServiceInterface;
use App\Contracts\Services\PurchaseServiceInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

/**
 * Inertia の共通設定。
 * 全画面に共有する props（ログインユーザー・相手方チップの履歴）を渡す。
 * 名前（1段目のキー）は camelCase、中身は snake_case（docs/conventions/10-api-conventions.md）。
 */
class HandleInertiaRequests extends Middleware
{
    /**
     * 初回表示で読み込むルートテンプレート（resources/views/app.blade.php）
     *
     * @var string
     */
    protected $rootView = 'app';

    private AuthServiceInterface $authService;

    private PurchaseServiceInterface $purchaseService;



    /**
     * AuthServiceInterface / PurchaseServiceInterface を注入する
     *
     * @param AuthServiceInterface $authService
     * @param PurchaseServiceInterface $purchaseService
     */
    public function __construct(AuthServiceInterface $authService, PurchaseServiceInterface $purchaseService)
    {
        $this->authService = $authService;
        $this->purchaseService = $purchaseService;
    }



    /**
     * 全画面に共有する props を返す
     *
     * @param Request $request
     * @return array
     */
    public function share(Request $request): array
    {
        //
        return [
            ...parent::share($request),
            // ログイン中のユーザー（未ログインは user_data=null）
            'auth' => fn() => $this->resolveAuth($request),
            // 相手方チップの履歴。全画面共通の「＋仕入」モーダルを開いた時だけ部分リロードで取りに来る
            // （在庫画面はページの props で常に渡す）
            'counterpartyList' => Inertia::optional(fn() => $this->resolveCounterpartyList($request)),
        ];
    }



    /**
     * ログイン状態を画面用に整形する
     *
     * @param Request $request
     * @return array
     */
    private function resolveAuth(Request $request): array
    {
        //
        $user = $request->user();
        if ($user !== null) {
            // ok.
        } else {
            //
            return [
                'user_data' => null,
            ];
        }

        //
        $authData = [
            'user_data' => $this->authService->getUserData((string)$user->id),
        ];
        return $authData;
    }



    /**
     * 相手方チップの履歴を画面用に整形する
     *
     * @param Request $request
     * @return array
     */
    private function resolveCounterpartyList(Request $request): array
    {
        //
        $user = $request->user();
        if ($user !== null) {
            // ok.
        } else {
            //
            return [];
        }

        //
        $result = $this->purchaseService->getCounterparties((string)$user->id);

        //
        return $result['list'];
    }
}
