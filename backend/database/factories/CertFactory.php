<?php

namespace Database\Factories;

use App\Models\Cert;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 证书工厂
 *
 * @extends Factory<Cert>
 */
class CertFactory extends Factory
{
    protected $model = Cert::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'action' => 'new',
            'channel' => 'api',
            'common_name' => fake()->domainName(),
            'alternative_names' => fake()->domainName(),
            'standard_count' => 1,
            'wildcard_count' => 0,
            'dcv' => [
                ['domain' => 'example.com', 'method' => 'txt'],
            ],
            'validation' => [],
            'documents' => [],
            'encryption_alg' => 'RSA',
            'encryption_bits' => 2048,
            'signature_digest_alg' => 'SHA256',
            'status' => 'pending',
        ];
    }

    /**
     * 签发中（已提交到 CA）
     */
    public function approving(): static
    {
        return $this->state([
            'status' => 'approving',
            'api_id' => fake()->uuid(),
            'vendor_id' => (string) fake()->randomNumber(8),
        ]);
    }

    /**
     * 已签发
     */
    public function active(): static
    {
        return $this->state([
            'status' => 'active',
            'api_id' => fake()->uuid(),
            'vendor_id' => (string) fake()->randomNumber(8),
            'issued_at' => now(),
            'expires_at' => now()->addYear(),
            'serial_number' => strtoupper(fake()->sha256()),
            'fingerprint' => strtoupper(fake()->sha256()),
        ]);
    }

    /**
     * 已过期
     */
    public function expired(): static
    {
        return $this->state([
            'status' => 'expired',
            'issued_at' => now()->subYear()->subDay(),
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * 已吊销
     */
    public function revoked(): static
    {
        return $this->state(['status' => 'revoked']);
    }

    /**
     * 已取消
     */
    public function cancelled(): static
    {
        return $this->state(['status' => 'cancelled']);
    }

    /**
     * 重签操作
     */
    public function reissue(): static
    {
        return $this->state(['action' => 'reissue']);
    }

    /**
     * 续费操作
     */
    public function renew(): static
    {
        return $this->state(['action' => 'renew']);
    }

    /**
     * ACME 通道
     */
    public function acme(): static
    {
        return $this->state(['channel' => 'acme']);
    }

    /**
     * 通配符证书
     */
    public function wildcard(): static
    {
        return $this->state([
            'common_name' => '*.'.fake()->domainName(),
            'wildcard_count' => 1,
            'standard_count' => 0,
        ]);
    }
}
