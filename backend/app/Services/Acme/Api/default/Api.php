<?php

declare(strict_types=1);

namespace App\Services\Acme\Api\default;

use App\Services\Acme\Api\AcmeSourceApiInterface;

class Api implements AcmeSourceApiInterface
{
    private Sdk $sdk;

    public function __construct()
    {
        $this->sdk = new Sdk;
    }

    public function prepareOrder(string $customer, string $productCode, ?string $referId = null): array
    {
        return $this->sdk->prepareOrder($customer, $productCode, $referId);
    }

    public function submitDomains(int $orderId, array $domains): array
    {
        return $this->sdk->submitDomains($orderId, $domains);
    }

    public function reissueOrder(int $orderId, array $domains, ?string $referId = null): array
    {
        return $this->sdk->reissueOrder($orderId, $domains, $referId);
    }

    public function respondToChallenge(int $challengeId): array
    {
        return $this->sdk->respondToChallenge($challengeId);
    }

    public function finalizeOrder(int $orderId, string $csr): array
    {
        return $this->sdk->finalizeOrder($orderId, $csr);
    }

    public function getCertificate(int $orderId): array
    {
        return $this->sdk->getCertificate($orderId);
    }

    public function cancelOrder(int $orderId): array
    {
        return $this->sdk->cancelOrder($orderId);
    }

    public function revokeCertificate(string $serialNumber, string $reason = 'UNSPECIFIED'): array
    {
        return $this->sdk->revokeCertificate($serialNumber, $reason);
    }

    public function isConfigured(): bool
    {
        return $this->sdk->isConfigured();
    }
}
