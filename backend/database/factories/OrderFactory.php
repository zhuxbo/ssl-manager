<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 订单工厂
 *
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'brand' => 'test brand',
            'period' => 12,
            'amount' => '100.00',
            'period_from' => now(),
            'period_till' => now()->addYear(),
            'purchased_standard_count' => 1,
            'purchased_wildcard_count' => 0,
            'organization' => null,
            'contact' => null,
            'remark' => '',
        ];
    }

    /**
     * 已取消
     */
    public function cancelled(): static
    {
        return $this->state(['cancelled_at' => now()]);
    }

    /**
     * 多域名订单
     */
    public function multiDomain(int $standardCount = 3, int $wildcardCount = 1): static
    {
        return $this->state([
            'purchased_standard_count' => $standardCount,
            'purchased_wildcard_count' => $wildcardCount,
        ]);
    }

    /**
     * 带组织信息
     */
    public function withOrganization(array $org = []): static
    {
        return $this->state([
            'organization' => array_merge([
                'name' => fake()->company(),
                'country' => 'CN',
                'state' => fake()->state(),
                'city' => fake()->city(),
            ], $org),
        ]);
    }

    /**
     * 带联系人信息
     */
    public function withContact(array $contact = []): static
    {
        return $this->state([
            'contact' => array_merge([
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'email' => fake()->safeEmail(),
                'phone' => fake()->phoneNumber(),
            ], $contact),
        ]);
    }

    /**
     * ACME 订单
     */
    public function acme(): static
    {
        return $this->state([
            'eab_kid' => fake()->uuid(),
            'eab_hmac' => fake()->sha256(),
        ]);
    }

    /**
     * 设置自动续费
     */
    public function autoRenew(bool $enabled = true): static
    {
        return $this->state(['auto_renew' => $enabled]);
    }

    /**
     * 设置自动重签
     */
    public function autoReissue(bool $enabled = true): static
    {
        return $this->state(['auto_reissue' => $enabled]);
    }
}
