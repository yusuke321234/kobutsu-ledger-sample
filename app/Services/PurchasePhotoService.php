<?php

namespace App\Services;

use App\Consts\PurchasePhotoConsts;
use App\Contracts\Repositories\PurchasePhotoRepositoryInterface;
use App\Contracts\Repositories\PurchaseRepositoryInterface;
use App\Contracts\Services\PurchasePhotoServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * 仕入の写真（1つの仕入につき5枚まで）。
 *
 * 実体は **Web 非公開の local ディスク**（storage/app/private）に置く。
 * 公開ディスクだと URL を知られただけで他人が古物の写真を見られてしまうため、
 * 配信は所有者チェック付きの認証ルート（GET /photos/{id}）から行う。
 */
class PurchasePhotoService implements PurchasePhotoServiceInterface
{
    private PurchasePhotoRepositoryInterface $purchasePhotoRepository;
    private PurchaseRepositoryInterface $purchaseRepository;

    /**
     * Repository を注入する
     *
     * @param PurchasePhotoRepositoryInterface $purchasePhotoRepository
     * @param PurchaseRepositoryInterface $purchaseRepository
     */
    public function __construct(
        PurchasePhotoRepositoryInterface $purchasePhotoRepository,
        PurchaseRepositoryInterface $purchaseRepository
    ) {
        $this->purchasePhotoRepository = $purchasePhotoRepository;
        $this->purchaseRepository = $purchaseRepository;
    }



    /**
     * 写真を登録する（1つの仕入につき5枚まで）。
     *
     * status は Controller が画面への伝え方を決める：ok / not_found / limit_exceeded
     *
     * @param string $userId
     * @param string $purchaseId
     * @param UploadedFile $file
     * @return array status / data
     */
    public function register(string $userId, string $purchaseId, UploadedFile $file): array
    {
        // 自分の仕入かを確認する
        Log::info("写真の登録先の仕入を確認します。 purchase_id: {$purchaseId}, user_id: {$userId}");
        $purchaseData = $this->purchaseRepository->getData($purchaseId, $userId);
        if ($purchaseData === null) {
            Log::info("登録先の仕入が見つかりませんでした。 purchase_id: {$purchaseId}");

            //
            return ['status' => 'not_found', 'data' => null];
        } else {
            Log::info("登録先の仕入が見つかりました。 purchase_id: {$purchaseId}");
        }

        // 枚数の上限を確認する
        $count = $this->purchasePhotoRepository->countByPurchaseId($purchaseId, $userId);
        Log::info("写真の枚数を確認します。 現在: {$count}, 上限: " . PurchasePhotoConsts::MAX_COUNT);
        if ($count >= PurchasePhotoConsts::MAX_COUNT) {
            Log::info("上限に達しているため保存しません。");

            //
            return ['status' => 'limit_exceeded', 'data' => null];
        } else {
            // ok.
        }

        // Web 非公開の local ディスクへ保存（ファイル名は Laravel が衝突しない乱数で付ける）
        $path = $file->store("purchase-photos/{$userId}", 'local');
        $photoId = $this->purchasePhotoRepository->create($userId, $purchaseId, (string)$path);
        Log::info("写真を登録しました。 photo_id: {$photoId}");

        //
        $result = [
            'status' => 'ok',
            'data' => [
                'id' => $photoId,
                // 認証付きの配信ルート。画面へは相対 URL（/photos/{id}）で渡す
                'url' => route('photos.show', ['id' => $photoId], false),
            ],
        ];
        return $result;
    }



    /**
     * 写真を削除する（行とファイルの両方）。写真は法定の記載事項ではないので物理削除でよい。
     *
     * @param string $userId
     * @param string $id
     * @return bool
     */
    public function delete(string $userId, string $id): bool
    {
        Log::info("削除対象の写真を確認します。 photo_id: {$id}, user_id: {$userId}");
        $photoData = $this->purchasePhotoRepository->getData($id, $userId);
        if ($photoData === null) {
            Log::info("削除対象の写真が見つかりませんでした。 photo_id: {$id}");

            //
            return false;
        } else {
            Log::info("削除対象の写真が見つかりました。 photo_id: {$id}");
        }

        // 先に行を消す（ファイル削除に失敗しても参照が残らないようにする）
        $this->purchasePhotoRepository->delete($id, $userId);
        Storage::disk('local')->delete((string)$photoData['path']);
        Log::info("写真を削除しました。 photo_id: {$id}");

        //
        return true;
    }



    /**
     * 配信用に写真の実ファイルパスを返す（本人のものでなく、実体が無ければ null）
     *
     * @param string $userId
     * @param string $id
     * @return string|null
     */
    public function getFilePath(string $userId, string $id): ?string
    {
        $photoData = $this->purchasePhotoRepository->getData($id, $userId);
        if ($photoData === null) {
            return null;
        } else {
            // ok.
        }

        $path = (string)$photoData['path'];
        if (Storage::disk('local')->exists($path) === true) {
            // ok.
        } else {
            Log::warning("写真の実体が見つかりません。 photo_id: {$id}, path: {$path}");

            //
            return null;
        }

        //
        return Storage::disk('local')->path($path);
    }
}
