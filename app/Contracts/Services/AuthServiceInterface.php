<?php

namespace App\Contracts\Services;

interface AuthServiceInterface
{
    public function register(array $userData): array;
    public function login(array $credentials, bool $remember): array;
    public function logout(): void;
    public function getUserData(string $userId): array;
    public function updateName(string $userId, array $nameData): void;
    public function updateOffice(string $userId, array $officeData): void;
}
