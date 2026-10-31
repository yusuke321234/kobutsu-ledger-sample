<?php

namespace Tests;

use App\Consts\FlashConsts;
use App\Consts\ToastConsts;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert as PHPUnit;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->registerInertiaAssertions();
    }



    /**
     * 画面（Inertia）の props を1項目ずつ確かめるアサーションを TestResponse に足す
     *
     * - assertInertiaProp('ledgerList.0.record_type', 'payout')（1段目は画面の props の名前＝camelCase、中身は snake_case）：値が一致する（型も含めて厳密に比べる）
     * - assertInertiaCount('ledgerList', 2)：配列の件数が一致する
     *
     * @return void
     */
    private function registerInertiaAssertions(): void
    {
        TestResponse::macro('assertInertiaProp', function (string $path, mixed $expected) {
            /** @var TestResponse $this */
            PHPUnit::assertSame($expected, $this->inertiaProps($path), "Inertia の props [{$path}] が一致しません。");

            //
            return $this;
        });

        TestResponse::macro('assertInertiaToast', function (string $message, string $type = ToastConsts::SUCCESS) {
            /** @var TestResponse $this */
            $this->assertInertiaFlash(FlashConsts::TOAST_DATA, ['type' => $type, 'message' => $message]);

            //
            return $this;
        });

        TestResponse::macro('assertInertiaCount', function (string $path, int $count) {
            /** @var TestResponse $this */
            PHPUnit::assertCount($count, (array)$this->inertiaProps($path), "Inertia の props [{$path}] の件数が一致しません。");

            //
            return $this;
        });
    }
}
