<?php

namespace App\Contracts\Repositories;

interface PurchaseRepositoryInterface
{
    public function getListByUserId(string $userId, int $limit, int $offset, array $filterData): array;
    public function countByUserId(string $userId, array $filterData): int;
    public function getData(string $id, string $userId): ?array;
    public function create(string $userId, array $createData): string;
    public function update(string $id, string $userId, array $updateData): void;
    public function delete(string $id, string $userId): bool;
    public function getDistinctCounterparties(string $userId): array;
    public function getForLedger(string $userId, ?string $from, ?string $to, string $q): array;
}
