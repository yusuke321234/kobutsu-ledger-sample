<?php

namespace App\Contracts\Services;

interface LedgerServiceInterface
{
    public function getList(string $userId, int $currentPage, int $pageSize, array $filterData): array;
    public function buildPdf(string $userId, array $filterData): string;
    public function buildCsv(string $userId, array $filterData): string;
}
