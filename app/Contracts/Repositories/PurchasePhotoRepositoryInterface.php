<?php

namespace App\Contracts\Repositories;

interface PurchasePhotoRepositoryInterface
{
    public function countByPurchaseId(string $purchaseId, string $userId): int;
    public function getData(string $id, string $userId): ?array;
    public function create(string $userId, string $purchaseId, string $path): string;
    public function delete(string $id, string $userId): bool;
}
