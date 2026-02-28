<?php

namespace Database\Factories;

use App\Models\SettingGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 设置组工厂
 *
 * @extends Factory<SettingGroup>
 */
class SettingGroupFactory extends Factory
{
    protected $model = SettingGroup::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->slug(2),
            'title' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'weight' => 0,
        ];
    }
}
