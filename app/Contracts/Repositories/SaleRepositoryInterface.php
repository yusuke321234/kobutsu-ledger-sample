<?php

namespace App\Contracts\Repositories;

interface SaleRepositoryInterface
{
    public function getForLedger(string $userId, ?string $from, ?string $to, string $q): array;
    public function getData(string $id, string $userId): ?array;
    public function create(string $userId, array $createData): string;
    public function update(string $id, string $userId, array $updateData): void;
    public function delete(string $id, string $userId): bool;
}
