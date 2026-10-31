<?php

namespace Tests\Feature;

use App\Consts\LogConsts;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ログの振り分け（storage/logs/年/月/日/web/コントローラ/メソッド.log）。
 * 実装は App\Logging\RequestPathLogHandler。
 */
class RequestLogTest extends TestCase
{
    use RefreshDatabase;

    private string $storageRoot;

    public function setUp(): void
    {
        parent::setUp();

        // 本物の storage/logs を汚さないよう、テスト用の置き場に書かせる
        $this->storageRoot = sys_get_temp_dir() . '/kobucho-request-log-' . uniqid();
        $this->app->useStoragePath($this->storageRoot);

        // テストでは既定でログを出さない（phpunit.xml）ので、このテストだけ振り分けのチャンネルを使う
        Log::setDefaultDriver('single');
    }



    protected function tearDown(): void
    {
        File::deleteDirectory($this->storageRoot);

        parent::tearDown();
    }



    /**
     * 今日（日本時間）のログファイルのパスを返す
     *
     * @param string $name
     * @return string
     */
    private function logPath(string $name): string
    {
        //
        return $this->storageRoot . '/logs/' . now(LogConsts::TIMEZONE)->format('Y/m/d') . '/' . $name . '.log';
    }



    #[Test]
    public function screenLogGoesToControllerAndMethodFileTest(): void
    {
        // GET /login → AuthController@showLogin
        $this->get(route('login'))->assertOk();

        //
        $this->assertFileExists($this->logPath('web/auth/show-login'));
        $this->assertStringContainsString('ログイン画面を表示します。', file_get_contents($this->logPath('web/auth/show-login')));
    }



    #[Test]
    public function sameUrlWithDifferentMethodsGoesToSeparateFilesTest(): void
    {
        $user = User::factory()->create();
        $purchase = Purchase::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);

        // POST /purchases と PUT /purchases/{id} は URL が同じでも、呼ばれるメソッドで別ファイルになる
        $this->from(route('home'))->post(route('purchases.register'), ['item_name' => 'カメラ']);
        $this->from(route('home'))->put(route('purchases.update', ['id' => $purchase->id]), ['item_name' => 'カメラ']);
        $this->from(route('home'))->delete(route('purchases.delete', ['id' => $purchase->id]));

        //
        $this->assertFileExists($this->logPath('web/purchase/register'));
        $this->assertFileExists($this->logPath('web/purchase/update'));
        $this->assertFileExists($this->logPath('web/purchase/delete'));
        // URL（ID）ごとのファイルは作らない
        $this->assertDirectoryDoesNotExist($this->storageRoot . '/logs/' . now(LogConsts::TIMEZONE)->format('Y/m/d') . '/web/purchases');
    }



    #[Test]
    public function validationErrorIsLoggedTest(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // 必須項目が足りない登録（FormRequest が失敗させる）
        $this->from(route('home'))->post(route('purchases.register'), ['item_name' => '']);

        // 失敗の理由は LogValidationError（ミドルウェア）が、そのリクエストのログに残す
        $logText = file_get_contents($this->logPath('web/purchase/register'));
        $this->assertStringContainsString('validation error.', $logText);
        $this->assertStringContainsString('item_name', $logText);
    }



    #[Test]
    public function controllerInSameFolderIsSeparatedByClassTest(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // PurchasePhotoController は PurchaseController と同じフォルダでも purchase-photo に分かれる
        $this->get(route('photos.show', ['id' => 999999]))->assertNotFound();

        //
        $this->assertFileExists($this->logPath('web/purchase-photo/show'));
    }



    #[Test]
    public function notFoundIsSeparatedTest(): void
    {
        // 存在しない URL（Route::fallback）
        $this->get('/no-such-page')->assertNotFound();

        //
        $this->assertFileExists($this->logPath(LogConsts::NOT_FOUND_NAME));
    }



    #[Test]
    public function counterpartyPersonalDataIsNotLoggedTest(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // 相手方の住所・職業・年齢はログに出さない（氏名は残す。だれとの取引かを追えるように）
        $this->from(route('home'))->post(route('purchases.register'), [
            'transaction_date' => '2026-07-01',
            'kubun' => '1',
            'item_name' => 'ジャンクカメラ',
            'quantity' => 1,
            'counterparty_name' => '山田太郎',
            'counterparty_address' => '秘密県秘密市1-2-3',
            'counterparty_occupation' => '秘密の職業',
            'counterparty_age' => '秘密の年齢',
            'verification_method' => '1',
            'price' => 3000,
            'is_exempt' => false,
            'is_draft' => false,
        ])->assertRedirect(route('home'));

        //
        $logText = file_get_contents($this->logPath('web/purchase/register'));
        $this->assertStringContainsString('山田太郎', $logText);
        $this->assertStringNotContainsString('秘密県秘密市', $logText);
        $this->assertStringNotContainsString('秘密の職業', $logText);
        $this->assertStringNotContainsString('秘密の年齢', $logText);
    }



    #[Test]
    public function logWithoutRequestGoesToConsoleFileTest(): void
    {
        // artisan schedule:run から書いた場合を再現する（ルートが無いところのログは console/コマンド名 へ）
        $originalArgv = $_SERVER['argv'];
        $_SERVER['argv'] = ['artisan', 'schedule:run'];
        Log::info('コマンドからのログ');
        $_SERVER['argv'] = $originalArgv;

        //
        $this->assertFileExists($this->logPath(LogConsts::CONSOLE_DIRECTORY . '/schedule-run'));
        $this->assertStringContainsString('コマンドからのログ', file_get_contents($this->logPath(LogConsts::CONSOLE_DIRECTORY . '/schedule-run')));
    }
}
