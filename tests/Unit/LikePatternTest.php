<?php

namespace Tests\Unit;

use App\Support\LikePattern;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * LIKE 検索の部分一致のパターン（DB を使わない純粋な関数なので Unit テストにする）。
 * 実際の検索で % と _ がその文字として扱われることは、Feature テスト（PurchaseTest・LedgerTest）で確かめる。
 */
class LikePatternTest extends TestCase
{
    #[Test]
    #[DataProvider('data_provider_for_containsTest')]
    public function containsTest(string $keyword, string $expected): void
    {
        //
        $this->assertSame($expected, LikePattern::contains($keyword));
    }
    public static function data_provider_for_containsTest(): array
    {
        return [
            // ふつうの文字はそのまま % で囲む
            'plain' => [
                'keyword' => 'カメラ',
                'expected' => '%カメラ%',
            ],
            // % と _ は ! でエスケープする
            'percent' => [
                'keyword' => '50%',
                'expected' => '%50!%%',
            ],
            'underscore' => [
                'keyword' => 'A_B',
                'expected' => '%A!_B%',
            ],
            // エスケープ文字の ! 自身もエスケープする（先に ! を置き換えないと、!% が !!% になって意味が変わる）
            'exclamation' => [
                'keyword' => '!%',
                'expected' => '%!!!%%',
            ],
        ];
    }
}
