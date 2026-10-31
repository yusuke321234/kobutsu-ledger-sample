<?php

namespace App\Repositories;

use App\Contracts\Repositories\PurchasePhotoRepositoryInterface;
use App\Models\PurchasePhoto;
use Illuminate\Support\Facades\Log;

class PurchasePhotoRepository implements PurchasePhotoRepositoryInterface
{
    private PurchasePhoto $purchasePhoto;

    /**
     * PurchasePhoto モデルを注入する
     *
     * @param PurchasePhoto $purchasePhoto
     */
    public function __construct(PurchasePhoto $purchasePhoto)
    {
        $this->purchasePhoto = $purchasePhoto;
    }



    /**
     * 仕入に紐づく写真の枚数を返す（本人のもののみ）
     *
     * @param string $purchaseId
     * @param string $userId
     * @return int
     */
    public function countByPurchaseId(string $purchaseId, string $userId): int
    {
        //
        $count = $this->purchasePhoto
            ->where('purchase_id', $purchaseId)
            ->where('user_id', $userId)
            ->count();

        //
        return $count;
    }



    /**
     * id と user_id で写真を取得する（本人のものでなければ null）
     *
     * @param string $id
     * @param string $userId
     * @return array|null
     */
    public function getData(string $id, string $userId): ?array
    {
        //
        $photo = $this->purchasePhoto->where('id', $id)->where('user_id', $userId)->first();

        //
        return $photo?->toArray();
    }



    /**
     * 写真を作成する
     *
     * @param string $userId
     * @param string $purchaseId
     * @param string $path
     * @return string 作成した写真の id
     */
    public function create(string $userId, string $purchaseId, string $path): string
    {
        Log::info("purchase_photo を新規作成します。 purchase_id: {$purchaseId}");

        //
        $result = $this->purchasePhoto->create([
            'user_id' => $userId,
            'purchase_id' => $purchaseId,
            'path' => $path,
        ]);

        //
        return (string)$result['id'];
    }



    /**
     * 写真を削除する（写真は法定の記載事項ではないので物理削除でよい。本人のもののみ）
     *
     * @param string $id
     * @param string $userId
     * @return bool
     */
    public function delete(string $id, string $userId): bool
    {
        //
        $deletedCount = $this->purchasePhoto->where('id', $id)->where('user_id', $userId)->delete();

        //
        return $deletedCount > 0;
    }
}
