<?php

declare(strict_types=1);

namespace App\Services\Acme\Api;

interface AcmeSourceApiInterface
{
    public function prepareOrder(string $customer, string $productCode, ?string $referId = null): array;

    public function submitDomains(int $orderId, array $domains): array;

    public function reissueOrder(int $orderId, array $domains, ?string $referId = null): array;

    public function respondToChallenge(int $challengeId): array;

    public function finalizeOrder(int $orderId, string $csr): array;

    public function getOrder(int $orderId): array;

    public function getOrderAuthorizations(int $orderId): array;

    public function getCertificate(int $orderId): array;

    public function cancelOrder(int $orderId): array;

    public function revokeCertificate(string $serialNumber, string $reason = 'UNSPECIFIED'): array;

    public function isConfigured(): bool;
}
