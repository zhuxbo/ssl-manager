<?php

namespace Database\Factories;

use App\Models\DeployToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * 部署令牌工厂
 *
 * @extends Factory<DeployToken>
 */
class DeployTokenFactory extends Factory
{
    protected $model = DeployToken::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token' => Str::random(64),
            'allowed_ips' => null,
            'rate_limit' => 60,
            'status' => 1,
        ];
    }

    /**
     * 禁用状态
     */
    public function disabled(): static
    {
        return $this->state(['status' => 0]);
    }

    /**
     * 限制 IP 访问
     */
    public function withAllowedIps(array $ips): static
    {
        return $this->state(['allowed_ips' => $ips]);
    }

    /**
     * 已使用过
     */
    public function used(): static
    {
        return $this->state([
            'last_used_at' => now(),
            'last_used_ip' => fake()->ipv4(),
        ]);
    }
}
