<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 发票工厂
 *
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => fake()->randomFloat(2, 100, 10000),
            'organization' => fake()->company(),
            'taxation' => fake()->numerify('##########'),
            'email' => fake()->safeEmail(),
            'remark' => '',
            'status' => 0,
        ];
    }

    /**
     * 已开具
     */
    public function issued(): static
    {
        return $this->state(['status' => 1]);
    }

    /**
     * 已作废
     */
    public function voided(): static
    {
        return $this->state(['status' => 2]);
    }
}
