<?php

namespace App\Http\Controllers\Purchase;

use App\Contracts\Services\PurchasePhotoServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * 仕入の写真（配信・削除）。
 * 追加は仕入の登録/更新と同じリクエストで受ける（PurchaseController）。
 */
class PurchasePhotoController extends Controller
{
    private PurchasePhotoServiceInterface $purchasePhotoService;



    /**
     * PurchasePhotoServiceInterface を注入する
     *
     * @param PurchasePhotoServiceInterface $purchasePhotoService
     */
    public function __construct(PurchasePhotoServiceInterface $purchasePhotoService)
    {
        $this->purchasePhotoService = $purchasePhotoService;
    }



    /**
     * 写真を削除する（行もファイルも消える）
     *
     * @param Request $request
     * @param string $id
     * @return RedirectResponse
     */
    public function delete(Request $request, string $id): RedirectResponse
    {
        // get
        $userId = (string)$request->user()->id;

        // log
        Log::info("user_id: {$userId}");
        Log::info("photo_id: {$id}");

        // execute
        $deleted = $this->purchasePhotoService->delete($userId, $id);

        // return（対象が無ければ404）
        if ($deleted === true) {
            Log::info("写真を削除しました。 photo_id: {$id}");
        } else {
            Log::info("削除対象の写真が見つからないため404を返します。 photo_id: {$id}");

            //
            abort(404, '対象の写真が見つかりません。');
        }

        //
        return back();
    }



    /**
     * 写真を配信する。
     * 実体は Web 非公開の local ディスクにあるので、本人だけがこのルート経由で取得できる。
     *
     * @param Request $request
     * @param string $id
     * @return BinaryFileResponse
     */
    public function show(Request $request, string $id): BinaryFileResponse
    {
        // get
        $userId = (string)$request->user()->id;

        // log
        Log::info("user_id: {$userId}");
        Log::info("photo_id: {$id}");

        // execute
        $filePath = $this->purchasePhotoService->getFilePath($userId, $id);

        // return（他人の写真・実体なしは404）
        if ($filePath !== null) {
            // ok.
        } else {
            Log::info("写真が見つからないため404を返します。 photo_id: {$id}, user_id: {$userId}");

            //
            abort(404, '写真が見つかりません。');
        }

        // 本人のみが取得できる私的なファイルなので、共有キャッシュに載らないよう private を明示する。
        // ヘッダ配列で 'Cache-Control' を渡すと Symfony 側で public に上書きされるため setPrivate() を使う。
        $response = response()->file($filePath);
        $response->setPrivate();
        $response->setMaxAge(3600);

        //
        return $response;
    }
}
