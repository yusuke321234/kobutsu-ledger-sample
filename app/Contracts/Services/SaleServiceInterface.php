<?php

namespace App\Contracts\Services;

interface SaleServiceInterface
{
    public function register(string $userId, array $registerData): array;
    public function update(string $userId, string $id, array $updateData): array;
    public function delete(string $userId, string $id): bool;
}
