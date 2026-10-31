<?php

namespace Tests\Feature;

use App\Consts\ToastConsts;
use App\Consts\ErrorConsts;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 画面の土台（ルートテンプレート・ゲストの誘導・エラー画面・死活監視）。
 */
class AppPageTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        // ビルド済みアセットが無くても画面の土台を確かめられるようにする
        $this->withoutVite();
    }



    #[Test]
    public function guestIsRedirectedToLoginTest(): void
    {
        // 在庫（ホーム）はログイン必須
        $this->get(route('home'))->assertRedirect(route('login'));
    }



    #[Test]
    public function rootTemplateRendersInertiaAppTest(): void
    {
        // 初回表示は Blade のルートテンプレートに、画面の初期データを埋め込んで返す
        $response = $this->get(route('login'));

        //
        $response->assertOk();
        $response->assertSee('<title', false);
        // 既定の説明（画面が開くと各ページの説明に置き換わるよう data-inertia で対応づける）
        $response->assertSee('<meta name="description"', false);
        $response->assertSee('data-inertia="description"', false);
        $response->assertSee('id="app"', false);
        $response->assertSee('/favicon.ico', false);
        $response->assertInertia(fn(Assert $page) => $page->component('auth/login')->etc());
    }



    #[Test]
    public function unknownUrlShowsNotFoundPageTest(): void
    {
        // 未ログインでも 404 画面（ゲスト用の見た目）
        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertInertia(fn(Assert $page) => $page
                ->component('error')
                ->where('status', 404)
                ->where('auth.user_data', null)
                ->etc());

        // ログイン中なら、ヘッダー付きの見た目で出せるようログインユーザーも渡す
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get('/no-such-page')
            ->assertNotFound()
            ->assertInertia(fn(Assert $page) => $page
                ->component('error')
                ->where('status', 404)
                ->where('auth.user_data.id', (string)$user->id)
                ->etc());
    }



    #[Test]
    public function unknownPhotoShowsNotFoundTest(): void
    {
        // 存在しない写真は 404（画面遷移ではないので中身は問わない）
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('photos.show', ['id' => 999999]))->assertNotFound();
    }



    #[Test]
    public function expiredSessionOnOperationReloadsLoginPageTest(): void
    {
        // 画面を開いたままセッションが切れて、仕入を登録しようとした
        $response = $this->withHeader('X-Inertia', 'true')->post(route('purchases.register'), ['item_name' => 'カメラ']);

        // ログイン画面をページごと開き直し、理由をトーストで伝える（操作は成功扱いにしない）
        $response->assertStatus(409);
        $response->assertHeader('X-Inertia-Location', route('login'));
        $response->assertInertiaToast('再度ログインしてください。', ToastConsts::ERROR);
        $this->assertDatabaseCount('purchases', 0);
    }



    #[Test]
    public function expiredSessionOnNavigationKeepsIntendedUrlTest(): void
    {
        // 画面を開いたままセッションが切れて、台帳へ移ろうとした
        $version = $this->get(route('login'))->inertiaPage()['version'];
        $response = $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string)$version,
        ])->get(route('ledger'));

        // ログイン後に台帳へ戻れるよう、行き先を覚えておく
        $response->assertStatus(409);
        $response->assertHeader('X-Inertia-Location', route('login'));
        $response->assertSessionHas('url.intended', route('ledger'));
        $response->assertInertiaToast('再度ログインしてください。', ToastConsts::ERROR);
    }



    #[Test]
    public function expiredPageOnOperationReturnsBackWithErrorTest(): void
    {
        // CSRF トークンの期限切れ（テストでは CSRF 検証が無効なので 419 を直接起こす）
        Route::post('/_test/expired-page', fn() => abort(419))->middleware('web');
        $user = User::factory()->create();

        //
        $response = $this->actingAs($user)
            ->withHeader('X-Inertia', 'true')
            ->from(route('ledger'))
            ->post('/_test/expired-page');

        // 元の画面へ戻して理由をトーストで伝え、操作は失敗扱い（エラー付き）にする
        $message = 'ページの有効期限が切れました。もう一度お試しください。';
        $response->assertRedirect(route('ledger'));
        $response->assertInertiaToast($message, ToastConsts::ERROR);
        $response->assertSessionHasErrors([ErrorConsts::REQUEST => $message]);
    }



    #[Test]
    public function healthCheckTest(): void
    {
        // 死活監視（/up）は画面の外で常に 200
        $this->get('/up')->assertOk();
    }
}
