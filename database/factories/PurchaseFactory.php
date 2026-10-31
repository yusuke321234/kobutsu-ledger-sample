<?php

namespace Database\Factories;

use App\Enums\Kubun;
use App\Enums\VerificationMethod;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    /**
     * モデルのデフォルト状態を定義する（確定済みの買受を1件）
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'transaction_date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'kubun' => Kubun::BUY,
            'item_name' => fake()->words(2, true),
            'quantity' => 1,
            'features' => fake()->optional()->sentence(),
            'counterparty_name' => fake()->company(),
            'counterparty_address' => fake()->prefecture() . fake()->city(),
            'counterparty_occupation' => fake()->optional()->word(),
            'counterparty_age' => (string)fake()->numberBetween(20, 70),
            'verification_method' => VerificationMethod::DEALER,
            'price' => fake()->numberBetween(500, 50000),
            'is_exempt' => false,
            'is_draft' => false,
        ];
    }

    /**
     * 下書き（現場捕獲＝金額のみ、様式15号の必須項目は未入力）
     *
     * @return static
     */
    public function draft(): static
    {
        return $this->state(fn(array $attributes): array => [
            'kubun' => Kubun::BUY,
            'item_name' => null,
            'features' => null,
            'counterparty_name' => null,
            'counterparty_address' => null,
            'counterparty_occupation' => null,
            'counterparty_age' => null,
            'verification_method' => null,
            'is_draft' => true,
        ]);
    }

    /**
     * 確認免除（1万円未満等）。相手方は未入力でよい。
     *
     * @return static
     */
    public function exempt(): static
    {
        return $this->state(fn(array $attributes): array => [
            'counterparty_name' => null,
            'counterparty_address' => null,
            'counterparty_occupation' => null,
            'counterparty_age' => null,
            'verification_method' => VerificationMethod::EXEMPT,
            'price' => fake()->numberBetween(100, 9999),
            'is_exempt' => true,
        ]);
    }
}
