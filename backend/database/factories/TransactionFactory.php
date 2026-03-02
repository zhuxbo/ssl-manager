<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 交易记录工厂
 *
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'addfunds',
            'transaction_id' => fake()->unique()->randomNumber(8),
            'amount' => fake()->randomFloat(2, 10, 10000),
            'balance_before' => '0.00',
            'balance_after' => '0.00',
            'remark' => '',
        ];
    }

    /**
     * 扣款类型
     */
    public function deduct(): static
    {
        return $this->state([
            'type' => 'deduct',
            'amount' => '-'.fake()->randomFloat(2, 10, 1000),
        ]);
    }

    /**
     * 订单消费
     */
    public function order(): static
    {
        return $this->state([
            'type' => 'order',
            'amount' => '-'.fake()->randomFloat(2, 10, 1000),
        ]);
    }

    /**
     * 冲正类型
     */
    public function reverse(): static
    {
        return $this->state([
            'type' => 'reverse',
            'amount' => fake()->randomFloat(2, 10, 1000),
        ]);
    }
}
