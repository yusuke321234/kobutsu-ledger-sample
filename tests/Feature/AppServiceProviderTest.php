<?php

namespace Tests\Feature;

use App\Models\Purchase;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * アプリ全体の既定値（AppServiceProvider::configureDefaults）のテスト
 */
class AppServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function destructiveCommandsAreProhibitedInProductionTest(): void
    {
        // 本番として起動し直す
        $user = User::factory()->create();
        $this->app['env'] = 'production';
        (new AppServiceProvider($this->app))->boot();

        //
        try {
            // DB を消すコマンドは実行されずに失敗する
            $this->artisan('migrate:fresh', ['--force' => true])->assertFailed();
            $this->artisan('db:wipe', ['--force' => true])->assertFailed();

            // 台帳の元データ（ユーザー）は消えていない
            $this->assertDatabaseHas('users', ['id' => $user->id]);
        } finally {
            // 禁止はクラスの共有状態なので、ほかのテストへ持ち越さない
            DB::prohibitDestructiveCommands(false);
        }
    }



    #[Test]
    public function datesAreImmutableTest(): void
    {
        //
        $purchase = Purchase::factory()->create(['transaction_date' => '2026-07-01']);

        // now() も Model の日付の列も、書き換えできない型で返る
        $this->assertInstanceOf(CarbonImmutable::class, now());
        $this->assertInstanceOf(CarbonImmutable::class, $purchase->fresh()->transaction_date);

        // 日付を足しても元の値は変わらない
        $start = $purchase->fresh()->transaction_date;
        $end = $start->addDay();
        $this->assertSame('2026-07-01', $start->toDateString());
        $this->assertSame('2026-07-02', $end->toDateString());
    }
}
