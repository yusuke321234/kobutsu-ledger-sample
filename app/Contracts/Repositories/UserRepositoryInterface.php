<?php

namespace App\Contracts\Repositories;

interface UserRepositoryInterface
{
    public function getData(string $id): ?array;
    public function create(array $createData): string;
    public function update(string $id, array $updateData): void;
}
