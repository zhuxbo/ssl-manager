<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 产品价格工厂
 *
 * @extends Factory<ProductPrice>
 */
class ProductPriceFactory extends Factory
{
    protected $model = ProductPrice::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'level_code' => 'standard',
            'period' => 12,
            'price' => fake()->randomFloat(2, 100, 5000),
            'alternative_standard_price' => '0.00',
            'alternative_wildcard_price' => '0.00',
        ];
    }

    /**
     * 包含 SAN 价格
     */
    public function withSanPrices(): static
    {
        return $this->state([
            'alternative_standard_price' => fake()->randomFloat(2, 50, 500),
            'alternative_wildcard_price' => fake()->randomFloat(2, 200, 2000),
        ]);
    }

    /**
     * 两年期
     */
    public function twoYear(): static
    {
        return $this->state(['period' => 24]);
    }

    /**
     * 免费
     */
    public function free(): static
    {
        return $this->state([
            'price' => '0.00',
            'alternative_standard_price' => '0.00',
            'alternative_wildcard_price' => '0.00',
        ]);
    }
}
