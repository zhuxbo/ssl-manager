<?php

namespace Plugins\Notice\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Plugins\Notice\Models\Notice;

class NoticeFactory extends Factory
{
    protected $model = Notice::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'content' => fake()->paragraph(),
            'type' => fake()->randomElement(['info', 'warning', 'success', 'error']),
            'is_active' => true,
            'sort' => fake()->numberBetween(0, 100),
        ];
    }
}
