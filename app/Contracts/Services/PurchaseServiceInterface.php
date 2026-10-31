<?php

namespace App\Contracts\Services;

interface PurchaseServiceInterface
{
    public function getList(string $userId, int $currentPage, int $pageSize, array $filterData): array;
    public function hasAny(string $userId): bool;
    public function register(string $userId, array $registerData): array;
    public function update(string $userId, string $id, array $updateData): ?array;
    public function delete(string $userId, string $id): array;
    public function getCounterparties(string $userId): array;
}
