<?php

namespace Database\Factories;

use App\Models\UserLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 用户等级工厂
 *
 * @extends Factory<UserLevel>
 */
class UserLevelFactory extends Factory
{
    protected $model = UserLevel::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(1),
            // user_levels.name 有唯一索引，避免批量创建时碰撞
            'name' => fake()->unique()->word().'会员',
            'custom' => 0,
            'cost_rate' => 1.0,
            'weight' => 0,
        ];
    }

    /**
     * 标准等级
     */
    public function standard(): static
    {
        return $this->state([
            'code' => 'standard',
            'name' => '标准会员',
            'cost_rate' => 1.0,
        ]);
    }

    /**
     * 高级等级（折扣）
     */
    public function premium(): static
    {
        return $this->state([
            'code' => 'premium',
            'name' => '高级会员',
            'cost_rate' => 0.9,
        ]);
    }

    /**
     * 自定义等级
     */
    public function custom(): static
    {
        return $this->state([
            'custom' => 1,
            'code' => 'custom_'.fake()->unique()->slug(1),
            'name' => '自定义等级-'.fake()->unique()->slug(1),
        ]);
    }
}
