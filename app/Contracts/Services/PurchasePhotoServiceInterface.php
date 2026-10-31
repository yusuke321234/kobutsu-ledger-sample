<?php

namespace App\Contracts\Services;

use Illuminate\Http\UploadedFile;

interface PurchasePhotoServiceInterface
{
    public function register(string $userId, string $purchaseId, UploadedFile $file): array;
    public function delete(string $userId, string $id): bool;
    public function getFilePath(string $userId, string $id): ?string;
}
