<?php

namespace Database\Factories\Acme;

use App\Models\Acme\AcmeCert;
use App\Models\Acme\AcmeOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class AcmeCertFactory extends Factory
{
    protected $model = AcmeCert::class;

    public function definition(): array
    {
        return [
            'order_id' => AcmeOrder::factory(),
            'action' => 'new',
            'channel' => 'api',
            'status' => 'processing',
            'common_name' => fake()->domainName(),
        ];
    }

    public function active(): static
    {
        return $this->state([
            'status' => 'active',
            'cert' => '-----BEGIN CERTIFICATE-----FAKE-----END CERTIFICATE-----',
            'issued_at' => now(),
            'expires_at' => now()->addYear(),
        ]);
    }

    public function unpaid(): static
    {
        return $this->state(['status' => 'unpaid']);
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending']);
    }

    public function withDomains(string $commonName = 'example.com', ?string $sans = null): static
    {
        return $this->state([
            'common_name' => $commonName,
            'alternative_names' => $sans ?? $commonName,
            'standard_count' => 1,
        ]);
    }
}
