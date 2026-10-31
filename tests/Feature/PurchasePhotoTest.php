<?php

namespace Tests\Feature;

use App\Models\Purchase;
use App\Models\PurchasePhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 仕入の写真（1つの仕入につき5枚まで）。
 * 追加は仕入の登録/更新と同じリクエスト（multipart）で受け、配信・削除は写真のルートで行う。
 */
class PurchasePhotoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;

    public function setUp(): void
    {
        parent::setUp();

        // 実ファイルを触らずに検証する（写真は Web 非公開の local ディスクに置く）
        Storage::fake('local');

        // ----- テストユーザー（本人 + 別ユーザー、Factory）-----
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    }



    /**
     * 仕入の確定済みペイロード（画面が multipart で送るときと同じく、真偽値は '1' / '0'）
     *
     * @return array
     */
    private function purchasePayload(): array
    {
        return [
            'transaction_date' => '2026-07-01',
            'kubun' => '1',
            'item_name' => 'ジャンクカメラ',
            'quantity' => '1',
            'counterparty_name' => 'ハードオフ西宮店',
            'verification_method' => '4',
            'price' => '3000',
            'is_exempt' => '0',
            'is_draft' => '0',
        ];
    }



    /**
     * 既存の仕入の更新と一緒に写真を1枚送る（multipart の POST を _method で PUT として扱わせる）
     *
     * @param string $purchaseId
     * @param string $fileName
     * @return TestResponse
     */
    private function uploadWithUpdate(string $purchaseId, string $fileName = 'item.jpg'): TestResponse
    {
        return $this->from(route('home'))->post(route('purchases.update', ['id' => $purchaseId]), array_merge($this->purchasePayload(), [
            '_method' => 'put',
            'photo' => UploadedFile::fake()->image($fileName, 800, 600),
        ]));
    }



    // ============================ 追加（仕入の登録・更新と一緒に） ============================

    #[Test]
    public function registerWithPhotoTest(): void
    {
        $this->actingAs($this->user);

        // 新規登録と同時に写真を保存する（仕入の id が決まってから紐づける）
        $response = $this->from(route('home'))->post(route('purchases.register'), array_merge($this->purchasePayload(), [
            'photo' => UploadedFile::fake()->image('item.jpg', 800, 600),
        ]));

        //
        $response->assertRedirect(route('home'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('purchases', 1);
        $this->assertDatabaseCount('purchase_photos', 1);

        // ファイルが local ディスクに保存され、仕入に紐づいている
        $photoData = PurchasePhoto::first();
        Storage::disk('local')->assertExists($photoData->path);
        $this->assertSame((string)Purchase::first()->id, (string)$photoData->purchase_id);
    }



    #[Test]
    public function canUploadPhotoTest(): void
    {
        $this->actingAs($this->user);
        $purchase = Purchase::factory()->create(['user_id' => $this->user->id]);

        //
        $response = $this->uploadWithUpdate((string)$purchase->id);

        //
        $response->assertRedirect(route('home'));
        $response->assertInertiaToast('保存しました。');
        $this->assertDatabaseCount('purchase_photos', 1);
        Storage::disk('local')->assertExists(PurchasePhoto::first()->path);
    }



    #[Test]
    public function canUploadUpToFivePhotosTest(): void
    {
        $this->actingAs($this->user);
        $purchase = Purchase::factory()->create(['user_id' => $this->user->id]);

        // 5枚までは保存できる
        for ($i = 1; $i <= 5; $i++) {
            $this->uploadWithUpdate((string)$purchase->id, "item{$i}.jpg")
                ->assertRedirect(route('home'))
                ->assertInertiaToast('保存しました。');
        }

        //
        $this->assertDatabaseCount('purchase_photos', 5);

        // 6枚目は上限超過。仕入の保存そのものは成立させ、写真だけ保存しない
        $response = $this->uploadWithUpdate((string)$purchase->id, 'item6.jpg');
        $response->assertRedirect(route('home'));
        $response->assertInertiaToast('保存しました。写真は1つの仕入につき5枚までです。');
        $this->assertDatabaseCount('purchase_photos', 5);
    }



    #[Test]
    public function cannotUploadToOtherUsersPurchaseTest(): void
    {
        $this->actingAs($this->user);
        $othersPurchase = Purchase::factory()->create(['user_id' => $this->otherUser->id]);

        //
        $response = $this->uploadWithUpdate((string)$othersPurchase->id);

        //
        $response->assertNotFound();
        $this->assertDatabaseCount('purchase_photos', 0);
    }



    #[Test]
    public function uploadValidatesFileTest(): void
    {
        $this->actingAs($this->user);
        $purchase = Purchase::factory()->create(['user_id' => $this->user->id]);

        // 画像でないファイルは弾く（拡張子ではなく MIME で判定）。仕入も保存しない
        $this->from(route('home'))->post(route('purchases.update', ['id' => $purchase->id]), array_merge($this->purchasePayload(), [
            '_method' => 'put',
            'item_name' => '変更後',
            'photo' => UploadedFile::fake()->create('note.pdf', 100, 'application/pdf'),
        ]))->assertRedirect(route('home'))->assertSessionHasErrors([
            'photo' => '画像ファイル（JPEG・PNG・WebP・HEIC）を選んでください。',
        ]);

        // 8MB 超は弾く
        $this->from(route('home'))->post(route('purchases.update', ['id' => $purchase->id]), array_merge($this->purchasePayload(), [
            '_method' => 'put',
            'photo' => UploadedFile::fake()->create('huge.jpg', 9000, 'image/jpeg'),
        ]))->assertRedirect(route('home'))->assertSessionHasErrors(['photo' => '写真は8MBまでです。']);

        // 写真を付けない保存は写真なしのまま
        $this->from(route('home'))->put(route('purchases.update', ['id' => $purchase->id]), $this->purchasePayload())
            ->assertRedirect(route('home'))
            ->assertSessionHasNoErrors();

        //
        $this->assertDatabaseCount('purchase_photos', 0);
        $this->assertDatabaseMissing('purchases', ['id' => $purchase->id, 'item_name' => '変更後']);
    }



    #[Test]
    public function uploadRequiresAuthTest(): void
    {
        $purchase = Purchase::factory()->create(['user_id' => $this->user->id]);
        $this->uploadWithUpdate((string)$purchase->id)->assertRedirect(route('login'));
        $this->assertDatabaseCount('purchase_photos', 0);
    }



    // ============================ 一覧への反映 ============================

    #[Test]
    public function photoAppearsInPurchaseListTest(): void
    {
        $this->actingAs($this->user);
        $purchase = Purchase::factory()->create(['user_id' => $this->user->id]);
        $this->uploadWithUpdate((string)$purchase->id)->assertRedirect(route('home'));
        $photoId = (string)PurchasePhoto::first()->id;

        // 実体は Web 非公開のまま、配信は認証ルート経由の URL を渡す
        $this->get(route('home'))
            ->assertInertia(fn(Assert $page) => $page
                ->has('purchaseList.0.photo_list', 1)
                ->where('purchaseList.0.photo_list.0.id', $photoId)
                ->where('purchaseList.0.photo_list.0.url', route('photos.show', ['id' => $photoId], false)));
    }



    // ============================ 配信（他人には見せない） ============================

    #[Test]
    public function ownerCanViewPhotoTest(): void
    {
        $this->actingAs($this->user);
        $purchase = Purchase::factory()->create(['user_id' => $this->user->id]);
        $this->uploadWithUpdate((string)$purchase->id);
        $photoId = PurchasePhoto::first()->id;

        //
        $response = $this->get(route('photos.show', ['id' => $photoId]));

        //
        $response->assertOk();
        // 本人だけのファイルなのでキャッシュは private に限定する
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
    }



    #[Test]
    public function otherUserCannotViewPhotoTest(): void
    {
        // 本人が写真を持つ
        $this->actingAs($this->user);
        $purchase = Purchase::factory()->create(['user_id' => $this->user->id]);
        $this->uploadWithUpdate((string)$purchase->id);
        $photoId = PurchasePhoto::first()->id;

        // 別ユーザーが URL を知っていても見られない（Web 非公開 + 所有者チェック）
        $this->actingAs($this->otherUser);
        $this->get(route('photos.show', ['id' => $photoId]))->assertNotFound();
    }



    #[Test]
    public function viewRequiresAuthTest(): void
    {
        $this->actingAs($this->user);
        $purchase = Purchase::factory()->create(['user_id' => $this->user->id]);
        $this->uploadWithUpdate((string)$purchase->id);
        $photoId = PurchasePhoto::first()->id;

        // 未認証では取得できない（ログイン画面へ）
        app('auth')->forgetGuards();
        $this->get(route('photos.show', ['id' => $photoId]))->assertRedirect(route('login'));
    }



    // ============================ 削除 ============================

    #[Test]
    public function ownerCanDeletePhotoTest(): void
    {
        $this->actingAs($this->user);
        $purchase = Purchase::factory()->create(['user_id' => $this->user->id]);
        $this->uploadWithUpdate((string)$purchase->id);
        $photoData = PurchasePhoto::first();

        //
        $response = $this->from(route('home'))->delete(route('photos.delete', ['id' => $photoData->id]));

        //
        $response->assertRedirect(route('home'));
        // 写真は法定の記載事項ではないので行もファイルも消す
        $this->assertDatabaseCount('purchase_photos', 0);
        Storage::disk('local')->assertMissing($photoData->path);
    }



    #[Test]
    public function cannotDeleteOtherUsersPhotoTest(): void
    {
        $this->actingAs($this->user);
        $purchase = Purchase::factory()->create(['user_id' => $this->user->id]);
        $this->uploadWithUpdate((string)$purchase->id);
        $photoId = PurchasePhoto::first()->id;

        //
        $this->actingAs($this->otherUser);
        $this->delete(route('photos.delete', ['id' => $photoId]))->assertNotFound();
        $this->assertDatabaseCount('purchase_photos', 1);
    }



    #[Test]
    public function deletingPhotoFreesSlotTest(): void
    {
        $this->actingAs($this->user);
        $purchase = Purchase::factory()->create(['user_id' => $this->user->id]);
        for ($i = 1; $i <= 5; $i++) {
            $this->uploadWithUpdate((string)$purchase->id, "item{$i}.jpg");
        }
        $photoId = PurchasePhoto::first()->id;
        $this->from(route('home'))->delete(route('photos.delete', ['id' => $photoId]))->assertRedirect(route('home'));

        // 消したらまた入れられる（枚数は「今ある枚数」で判定する）
        $this->uploadWithUpdate((string)$purchase->id, 'again.jpg')->assertInertiaToast('保存しました。');
        $this->assertDatabaseCount('purchase_photos', 5);
    }
}
