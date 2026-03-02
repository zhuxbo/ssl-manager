<?php

namespace Database\Factories;

use App\Models\Chain;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
            // 避免命中 chains.common_name 唯一索引导致测试随机失败
            'common_name' => fake()->domainWord().'-'.Str::lower((string) Str::uuid()).' CA',
            'intermediate_cert' => '-----BEGIN CERTIFICATE-----'."\n"
                .fake()->sha256()."\n"
                .'-----END CERTIFICATE-----',
        ];
    }
}
