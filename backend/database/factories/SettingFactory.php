<?php

namespace Database\Factories;

use App\Models\Setting;
use App\Models\SettingGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 设置项工厂
 *
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition(): array
    {
        return [
            'group_id' => SettingGroup::factory(),
            'key' => fake()->unique()->slug(2),
            'type' => 'string',
            'value' => fake()->word(),
            'description' => fake()->sentence(),
            'weight' => 0,
        ];
    }

    /**
     * 整数类型
     */
    public function integer(): static
    {
        return $this->state([
            'type' => 'integer',
            'value' => (string) fake()->randomNumber(3),
        ]);
    }

    /**
     * 布尔类型
     */
    public function boolean(): static
    {
        return $this->state([
            'type' => 'boolean',
            'value' => fake()->boolean() ? '1' : '0',
        ]);
    }

    /**
     * 数组类型
     */
    public function array(): static
    {
        return $this->state([
            'type' => 'array',
            'value' => json_encode([fake()->word(), fake()->word()]),
        ]);
    }

    /**
     * 下拉选择类型
     */
    public function select(array $options = [], bool $multiple = false): static
    {
        $opts = $options ?: [fake()->word(), fake()->word(), fake()->word()];

        return $this->state([
            'type' => 'select',
            'options' => $opts,
            'is_multiple' => $multiple,
            'value' => $multiple ? json_encode([$opts[0]]) : $opts[0],
        ]);
    }
}
