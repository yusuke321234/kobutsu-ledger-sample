<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    public function setUp(): void
    {
        parent::setUp();

        // ----- ログイン用の既知ユーザー（password は Factory 既定の 'password'）-----
        $this->user = User::factory()->create([
            'family_name' => '山田',
            'given_name' => '太郎',
            'email' => 'existing@example.com',
        ]);
    }

    #[Test]
    #[DataProvider('data_provider_for_showGuestPageTest')]
    public function showGuestPageTest(string $routeName, array $query, string $component): void
    {
        //
        $url = route($routeName, $query);

        // 未ログインなら画面を返す
        $this->get($url)
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component($component)
                ->where('auth.user_data', null)
                ->etc());

        // ログイン済みなら在庫（ホーム）へ戻す
        $this->actingAs($this->user)->get($url)->assertRedirect(route('home'));
    }
    public static function data_provider_for_showGuestPageTest(): array
    {
        return [
            'login' => [
                'routeName' => 'login',
                'query' => [],
                'component' => 'auth/login',
            ],
            'register' => [
                'routeName' => 'register',
                'query' => [],
                'component' => 'auth/register',
            ],
        ];
    }



    #[Test]
    #[DataProvider('data_provider_for_registerTest')]
    public function registerTest(string $testCase, array $payload, bool $expectsSuccess, array $expectedErrorKeyList): void
    {
        // 画面はサーバーと同じ項目名（snake_case）で送る
        $response = $this->from(route('register'))->post(route('register.submit'), $payload);

        //
        if ($expectsSuccess === true) {
            // 登録後はログイン状態で在庫（ホーム）へ
            $response->assertRedirect(route('home'));
            $response->assertSessionHasNoErrors();
            $this->assertAuthenticated();
            $this->assertDatabaseHas('users', [
                'email' => $payload['email'],
                'family_name' => $payload['family_name'],
            ]);

            // パスワードはハッシュで保存される
            $this->assertTrue(Hash::check($payload['password'], User::where('email', $payload['email'])->value('password')));
        } else {
            // 入力画面へ戻し、該当項目のエラーを載せる
            $response->assertRedirect(route('register'));
            $response->assertSessionHasErrors($expectedErrorKeyList);
            $this->assertGuest();
        }
    }
    public static function data_provider_for_registerTest(): array
    {
        return [
            'success' => [
                'testCase' => 'success',
                'payload' => [
                    'family_name' => '新規',
                    'given_name' => '花子',
                    'email' => 'new@example.com',
                    'password' => 'password123',
                ],
                'expectsSuccess' => true,
                'expectedErrorKeyList' => [],
            ],
            'email_taken' => [
                'testCase' => 'email_taken',
                'payload' => [
                    'family_name' => '重複',
                    'given_name' => '太郎',
                    'email' => 'existing@example.com',
                    'password' => 'password123',
                ],
                'expectsSuccess' => false,
                'expectedErrorKeyList' => ['email'],
            ],
            'missing_family_name' => [
                'testCase' => 'missing_family_name',
                'payload' => [
                    'given_name' => '花子',
                    'email' => 'new@example.com',
                    'password' => 'password123',
                ],
                'expectsSuccess' => false,
                'expectedErrorKeyList' => ['family_name'],
            ],
            'short_password' => [
                'testCase' => 'short_password',
                'payload' => [
                    'family_name' => '新規',
                    'given_name' => '花子',
                    'email' => 'new@example.com',
                    'password' => 'short',
                ],
                'expectsSuccess' => false,
                'expectedErrorKeyList' => ['password'],
            ],
        ];
    }



    #[Test]
    public function registerErrorsKeepSnakeCaseForPageTest(): void
    {
        // 入力エラーは、サーバーと同じ項目名（snake_case）で届く＝画面のフォームの項目名と一致する
        $this->from(route('register'))->post(route('register.submit'), ['email' => 'new@example.com']);

        //
        $this->get(route('register'))
            ->assertInertia(fn(Assert $page) => $page
                ->has('errors.family_name')
                ->has('errors.given_name')
                ->has('errors.password')
                ->missing('errors.familyName'));
    }



    #[Test]
    #[DataProvider('data_provider_for_loginTest')]
    public function loginTest(string $testCase, array $payload, bool $expectsSuccess, string $expectedErrorKey): void
    {
        //
        $response = $this->from(route('login'))->post(route('login.submit'), $payload);

        //
        if ($expectsSuccess === true) {
            $response->assertRedirect(route('home'));
            $this->assertAuthenticatedAs($this->user);
        } else {
            $response->assertRedirect(route('login'));
            $response->assertSessionHasErrors($expectedErrorKey);
            $this->assertGuest();
        }

        // 認証に失敗したときは、メールアドレス欄に理由を出す（存在有無は区別しない）
        if ($testCase === 'wrong_password' || $testCase === 'unknown_email') {
            $response->assertSessionHasErrors(['email' => 'メールアドレスまたはパスワードが違います。']);
        } else {
            // no action.
        }
    }
    public static function data_provider_for_loginTest(): array
    {
        return [
            'success' => [
                'testCase' => 'success',
                'payload' => [
                    'email' => 'existing@example.com',
                    'password' => 'password',
                    'remember' => true,
                ],
                'expectsSuccess' => true,
                'expectedErrorKey' => '',
            ],
            'wrong_password' => [
                'testCase' => 'wrong_password',
                'payload' => [
                    'email' => 'existing@example.com',
                    'password' => 'wrong-password',
                ],
                'expectsSuccess' => false,
                'expectedErrorKey' => 'email',
            ],
            'unknown_email' => [
                'testCase' => 'unknown_email',
                'payload' => [
                    'email' => 'nobody@example.com',
                    'password' => 'password',
                ],
                'expectsSuccess' => false,
                'expectedErrorKey' => 'email',
            ],
            'missing_password' => [
                'testCase' => 'missing_password',
                'payload' => [
                    'email' => 'existing@example.com',
                ],
                'expectsSuccess' => false,
                'expectedErrorKey' => 'password',
            ],
        ];
    }



    #[Test]
    public function loginRedirectsToIntendedPageTest(): void
    {
        // ログイン前に開こうとした画面は、ログイン画面へ誘導される
        $this->get(route('ledger'))->assertRedirect(route('login'));

        // ログインするとその画面へ戻る
        $this->post(route('login.submit'), [
            'email' => 'existing@example.com',
            'password' => 'password',
        ])->assertRedirect(route('ledger'));
    }



    #[Test]
    #[DataProvider('data_provider_for_throttleTest')]
    public function throttleTest(string $routeName, array $payload, int $limit): void
    {
        // 上限までは通る（成否は問わず、制限のエラーになっていないことだけを見る）
        $message = 'お試し回数が上限に達しました。しばらく時間をおいてからもう一度お試しください。';
        for ($i = 1; $i <= $limit; $i++) {
            $response = $this->from(route('login'))->post(route($routeName), $payload);
            $response->assertSessionDoesntHaveErrors(['email' => $message]);
        }

        // 上限を超えた分は入力画面へ戻し、メールアドレス欄に日本語メッセージを出す（Retry-After も返す）
        $response = $this->from(route('login'))->post(route($routeName), $payload);
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email' => $message]);
        $response->assertHeader('Retry-After');
    }
    public static function data_provider_for_throttleTest(): array
    {
        return [
            'login' => [
                'routeName' => 'login.submit',
                'payload' => [
                    'email' => 'existing@example.com',
                    'password' => 'wrong-password',
                ],
                'limit' => 5,
            ],
            'register' => [
                'routeName' => 'register.submit',
                'payload' => [
                    'family_name' => '新規',
                    'given_name' => '花子',
                    'email' => 'existing@example.com',
                    'password' => 'password123',
                ],
                'limit' => 5,
            ],
        ];
    }



    #[Test]
    public function throttleReturnsJsonForJsonRequestTest(): void
    {
        // JSON を求める要求には 429 と日本語メッセージを返す
        $payload = [
            'email' => 'existing@example.com',
            'password' => 'wrong-password',
        ];
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson(route('login.submit'), $payload);
        }

        //
        $this->postJson(route('login.submit'), $payload)
            ->assertStatus(429)
            ->assertJsonPath('message', 'お試し回数が上限に達しました。しばらく時間をおいてからもう一度お試しください。');
    }



    #[Test]
    public function throttleCredentialStuffingTest(): void
    {
        // メールアドレスを変えれば1件あたりの上限（5回/分）には掛からないが、
        // 同一 IP からの試行そのものを 20回/分で止める（クレデンシャルスタッフィング対策）
        for ($i = 1; $i <= 20; $i++) {
            $payload = [
                'email' => "victim{$i}@example.com",
                'password' => 'wrong-password',
            ];
            $response = $this->postJson(route('login.submit'), $payload);
            $this->assertNotSame(429, $response->getStatusCode(), "{$i} 回目で 429 になりました。");
        }

        //
        $payload = [
            'email' => 'victim21@example.com',
            'password' => 'wrong-password',
        ];
        $this->postJson(route('login.submit'), $payload)->assertStatus(429);
    }



    #[Test]
    public function logoutTest(): void
    {
        // 実ログインでセッション（web ガード）を確立
        $this->post(route('login.submit'), [
            'email' => 'existing@example.com',
            'password' => 'password',
        ])->assertRedirect(route('home'));
        $this->assertAuthenticated('web');

        // ログアウトでセッションが破棄され、ログイン画面へ
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest('web');
    }



    #[Test]
    #[DataProvider('data_provider_for_showSettingsTest')]
    public function showSettingsTest(string $testCase): void
    {
        //
        if ($testCase === 'authenticated') {
            $this->actingAs($this->user)
                ->get(route('settings'))
                ->assertOk()
                ->assertInertia(fn(Assert $page) => $page
                    ->component('settings')
                    // ログインユーザーは全画面共有の props で届く（中身は snake_case・ID は string）
                    ->where('auth.user_data.id', (string)$this->user->id)
                    ->where('auth.user_data.email', 'existing@example.com')
                    ->where('auth.user_data.family_name', '山田')
                    ->where('auth.user_data.given_name', '太郎')
                    ->where('auth.user_data.office_name', null)
                    ->where('auth.user_data.license_number', null)
                    ->missing('auth.user_data.password'));
        } else {
            // 未ログインはログイン画面へ
            $this->get(route('settings'))->assertRedirect(route('login'));
        }
    }
    public static function data_provider_for_showSettingsTest(): array
    {
        return [
            'authenticated' => [
                'testCase' => 'authenticated',
            ],
            'unauthenticated' => [
                'testCase' => 'unauthenticated',
            ],
        ];
    }



    #[Test]
    #[DataProvider('data_provider_for_updateNameTest')]
    public function updateNameTest(string $testCase, array $payload, bool $expectsSuccess): void
    {
        // 認証状態を用意（未認証ケースのみ actingAs しない）
        if ($testCase === 'unauthenticated') {
            // no action.（未認証のまま叩く）
        } else {
            $this->actingAs($this->user);
        }

        //
        $response = $this->from(route('settings'))->put(route('user.name.update'), $payload);

        //
        if ($expectsSuccess === true) {
            $response->assertRedirect(route('settings'));
            $response->assertInertiaToast('氏名を変更しました。');
            $this->assertDatabaseHas('users', [
                'id' => $this->user->id,
                'family_name' => '佐藤',
                'given_name' => '次郎',
            ]);
        } else if ($testCase === 'unauthenticated') {
            $response->assertRedirect(route('login'));
            $this->assertDatabaseHas('users', ['id' => $this->user->id, 'family_name' => '山田']);
        } else {
            $response->assertRedirect(route('settings'));
            $response->assertSessionHasErrors('family_name');
            $this->assertDatabaseHas('users', ['id' => $this->user->id, 'family_name' => '山田']);
        }
    }
    public static function data_provider_for_updateNameTest(): array
    {
        return [
            'success' => [
                'testCase' => 'success',
                'payload' => [
                    'family_name' => '佐藤',
                    'given_name' => '次郎',
                ],
                'expectsSuccess' => true,
            ],
            'unauthenticated' => [
                'testCase' => 'unauthenticated',
                'payload' => [
                    'family_name' => '佐藤',
                    'given_name' => '次郎',
                ],
                'expectsSuccess' => false,
            ],
            'missing_family_name' => [
                'testCase' => 'missing_family_name',
                'payload' => [
                    'given_name' => '次郎',
                ],
                'expectsSuccess' => false,
            ],
        ];
    }



    #[Test]
    public function updateNameDoesNotWipeOfficeInfoTest(): void
    {
        $this->user->forceFill(['office_name' => 'コブチョウ商店', 'license_number' => '第123456789012号'])->save();
        $this->actingAs($this->user);

        // 氏名の変更は営業所情報に触れない
        $response = $this->from(route('settings'))->put(route('user.name.update'), [
            'family_name' => '佐藤',
            'given_name' => '次郎',
        ]);

        //
        $response->assertRedirect(route('settings'));
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'family_name' => '佐藤',
            'office_name' => 'コブチョウ商店',
            'license_number' => '第123456789012号',
        ]);
    }



    #[Test]
    #[DataProvider('data_provider_for_updateOfficeTest')]
    public function updateOfficeTest(string $testCase, array $payload, bool $expectsSuccess): void
    {
        // 認証状態を用意（未認証ケースのみ actingAs しない）
        if ($testCase === 'unauthenticated') {
            // no action.（未認証のまま叩く）
        } else {
            $this->actingAs($this->user);
        }

        // 営業所の2項目だけを送る（氏名は送らない）
        $response = $this->from(route('settings'))->put(route('user.office.update'), $payload);

        //
        if ($expectsSuccess === true) {
            $response->assertRedirect(route('settings'));
            $response->assertInertiaToast('営業所情報を保存しました。');
            $this->assertDatabaseHas('users', [
                'id' => $this->user->id,
                'office_name' => 'コブチョウ商店',
                'license_number' => '第123456789012号',
                'family_name' => '山田',
                'given_name' => '太郎',
            ]);

            // 画面の共有 props にも反映される
            $this->get(route('settings'))
                ->assertInertia(fn(Assert $page) => $page
                    ->where('auth.user_data.office_name', 'コブチョウ商店')
                    ->where('auth.user_data.license_number', '第123456789012号'));
        } else if ($testCase === 'unauthenticated') {
            $response->assertRedirect(route('login'));
            $this->assertDatabaseHas('users', ['id' => $this->user->id, 'office_name' => null]);
        } else {
            $response->assertRedirect(route('settings'));
            $response->assertSessionHasErrors('office_name');
            $this->assertDatabaseHas('users', ['id' => $this->user->id, 'office_name' => null]);
        }
    }
    public static function data_provider_for_updateOfficeTest(): array
    {
        return [
            'success' => [
                'testCase' => 'success',
                'payload' => [
                    'office_name' => 'コブチョウ商店',
                    'license_number' => '第123456789012号',
                ],
                'expectsSuccess' => true,
            ],
            'unauthenticated' => [
                'testCase' => 'unauthenticated',
                'payload' => [
                    'office_name' => 'コブチョウ商店',
                    'license_number' => '第123456789012号',
                ],
                'expectsSuccess' => false,
            ],
            'too_long_office_name' => [
                'testCase' => 'too_long_office_name',
                'payload' => [
                    'office_name' => str_repeat('あ', 256),
                    'license_number' => '第123456789012号',
                ],
                'expectsSuccess' => false,
            ],
        ];
    }



    #[Test]
    public function updateOfficeCanClearWithEmptyStringTest(): void
    {
        $this->user->forceFill(['office_name' => 'コブチョウ商店', 'license_number' => '第123456789012号'])->save();
        $this->actingAs($this->user);

        // 空で送った場合は「未設定に戻す」意図として null にする
        $response = $this->from(route('settings'))->put(route('user.office.update'), [
            'office_name' => '',
            'license_number' => '',
        ]);

        //
        $response->assertRedirect(route('settings'));
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'office_name' => null,
            'license_number' => null,
            'family_name' => '山田',
        ]);
    }
}
