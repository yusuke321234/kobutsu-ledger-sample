<?php

namespace Tests\Unit;

use App\Support\DateText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * DB から取り出した日付・日時の整形（DB を使わない純粋な関数なので Unit テストにする）。
 */
class DateTextTest extends TestCase
{
    #[Test]
    #[DataProvider('data_provider_for_toDateStringTest')]
    public function toDateStringTest(?string $value, ?string $expected): void
    {
        //
        $this->assertSame($expected, DateText::toDateString($value));
    }
    public static function data_provider_for_toDateStringTest(): array
    {
        return [
            // Model の toArray() は date の列を ISO 8601 の文字列にする
            'iso8601' => [
                'value' => '2026-07-10T00:00:00.000000Z',
                'expected' => '2026-07-10',
            ],
            'date_only' => [
                'value' => '2026-07-10',
                'expected' => '2026-07-10',
            ],
            // 未入力は未入力のまま
            'null' => [
                'value' => null,
                'expected' => null,
            ],
        ];
    }



    #[Test]
    public function toDateTimeStringTest(): void
    {
        //
        $this->assertSame('2026-07-10 12:34:56', DateText::toDateTimeString('2026-07-10T12:34:56.000000Z'));
        $this->assertNull(DateText::toDateTimeString(null));
    }
}
