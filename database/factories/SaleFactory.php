<?php

namespace Database\Factories;

use App\Enums\SalesChannel;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * モデルのデフォルト状態を定義する（メルカリで売却した1件）
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'purchase_id' => Purchase::factory(),
            'sale_date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'channel' => SalesChannel::MERCARI,
            'sale_price' => fake()->numberBetween(1000, 80000),
            'fee' => fake()->numberBetween(0, 5000),
            'shipping_cost' => fake()->numberBetween(0, 1500),
            'counterparty_name' => null,
            'counterparty_address' => null,
        ];
    }
}
