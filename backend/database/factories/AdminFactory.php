<?php

namespace Database\Factories;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 管理员工厂
 *
 * @extends Factory<Admin>
 */
class AdminFactory extends Factory
{
    protected $model = Admin::class;

    public function definition(): array
    {
        return [
            'username' => 'a_'.fake()->unique()->numerify('######'),
            'email' => fake()->unique()->safeEmail(),
            // admins.mobile 有唯一索引，使用唯一数字串避免批量建数时碰撞
            'mobile' => fake()->unique()->numerify('1##########'),
            'password' => 'password',
            'token_version' => 0,
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
     * 已登录过
     */
    public function loggedIn(): static
    {
        return $this->state([
            'last_login_at' => now(),
            'last_login_ip' => fake()->ipv4(),
        ]);
    }
}
