<?php

namespace Database\Factories;

use App\Models\Chain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 证书链工厂
 *
 * @extends Factory<Chain>
 */
class ChainFactory extends Factory
{
    protected $model = Chain::class;

    public function definition(): array
    {
        return [
            'common_name' => fake()->domainName().' CA',
            'intermediate_cert' => '-----BEGIN CERTIFICATE-----'."\n"
                .fake()->sha256()."\n"
                .'-----END CERTIFICATE-----',
        ];
    }
}
