<?php

namespace Database\Factories;

use App\Models\InvoiceLimit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 发票额度工厂
 *
 * @extends Factory<InvoiceLimit>
 */
class InvoiceLimitFactory extends Factory
{
    protected $model = InvoiceLimit::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'addfunds',
            'limit_id' => fake()->unique()->randomNumber(8),
            'amount' => fake()->randomFloat(2, 100, 10000),
            'limit_before' => '0.00',
            'limit_after' => '0.00',
        ];
    }

    /**
     * 开票类型
     */
    public function issue(): static
    {
        return $this->state([
            'type' => 'issue',
            'amount' => '-'.fake()->randomFloat(2, 100, 5000),
        ]);
    }

    /**
     * 作废类型（退回额度）
     */
    public function void(): static
    {
        return $this->state([
            'type' => 'void',
            'amount' => fake()->randomFloat(2, 100, 5000),
        ]);
    }
}
