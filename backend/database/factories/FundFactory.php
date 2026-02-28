<?php

namespace Database\Factories;

use App\Models\Fund;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 资金工厂
 *
 * @extends Factory<Fund>
 */
class FundFactory extends Factory
{
    protected $model = Fund::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => fake()->randomFloat(2, 10, 10000),
            'type' => 'addfunds',
            'pay_method' => 'alipay',
            'pay_sn' => fake()->unique()->numerify('PAY##############'),
            'ip' => fake()->ipv4(),
            'remark' => '',
            'status' => 0,
        ];
    }

    /**
     * 已完成
     */
    public function completed(): static
    {
        return $this->state(['status' => 1]);
    }

    /**
     * 已退款
     */
    public function refunded(): static
    {
        return $this->state(['status' => 2]);
    }

    /**
     * 扣款类型
     */
    public function deduct(): static
    {
        return $this->state(['type' => 'deduct']);
    }

    /**
     * 冲正类型
     */
    public function reverse(): static
    {
        return $this->state(['type' => 'reverse']);
    }

    /**
     * 微信支付
     */
    public function wechat(): static
    {
        return $this->state(['pay_method' => 'wechat']);
    }

    /**
     * 信用额度
     */
    public function credit(): static
    {
        return $this->state(['pay_method' => 'credit']);
    }
}
