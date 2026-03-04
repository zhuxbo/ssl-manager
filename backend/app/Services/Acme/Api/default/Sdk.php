<?php

declare(strict_types=1);

namespace App\Services\Acme\Api\default;

use App\Models\CaLog;
use App\Services\LogBuffer;
use Illuminate\Support\Facades\Http;

class Sdk
{
    private string $baseUrl;

    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = $this->resolveBaseUrl();
        // 仅当 acmeToken 为 null 时回落到 token，空字符串不回落（设计意图：允许显式置空以禁用）
        $this->apiKey = trim((string) (get_system_setting('ca', 'acmeToken') ?? get_system_setting('ca', 'token') ?? ''));
    }

    /**
     * 解析 ACME API 地址：优先 acmeUrl，回落 url 并替换路径
     */
    private function resolveBaseUrl(): string
    {
        $acmeUrl = get_system_setting('ca', 'acmeUrl');
        if ($acmeUrl) {
            return rtrim($acmeUrl, '/');
        }

        $url = get_system_setting('ca', 'url') ?? '';
        if ($url === '') {
            return '';
        }

        return rtrim(preg_replace('#/api/v\d+#', '/api/acme', $url), '/');
    }

    public function createOrder(string $customer, string $productCode, array $domains, ?string $referId = null): array
    {
        $data = [
            'customer' => $customer,
            'product_code' => $productCode,
            'domains' => $domains,
        ];
        if ($referId) {
            $data['refer_id'] = $referId;
        }

        return $this->request('POST', '/orders', $data);
    }

    public function reissueOrder(int $orderId, array $domains, ?string $referId = null): array
    {
        $data = [
            'domains' => $domains,
        ];
        if ($referId) {
            $data['refer_id'] = $referId;
        }

        return $this->request('POST', "/orders/reissue/$orderId", $data);
    }

    public function getOrder(int $orderId): array
    {
        return $this->request('GET', "/orders/$orderId");
    }

    public function getOrderAuthorizations(int $orderId): array
    {
        return $this->request('GET', "/orders/authorizations/$orderId");
    }

    public function respondToChallenge(int $challengeId): array
    {
        return $this->request('POST', "/challenges/respond/$challengeId");
    }

    public function finalizeOrder(int $orderId, string $csr): array
    {
        return $this->request('POST', "/orders/finalize/$orderId", [
            'csr' => $csr,
        ]);
    }

    public function getCertificate(int $orderId): array
    {
        return $this->request('GET', "/orders/certificate/$orderId");
    }

    public function cancelOrder(int $orderId): array
    {
        return $this->request('DELETE', "/orders/$orderId");
    }

    public function revokeCertificate(string $serialNumber, string $reason = 'UNSPECIFIED'): array
    {
        return $this->request('POST', '/certificates/revoke', [
            'serial_number' => $serialNumber,
            'reason' => $reason,
        ]);
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->apiKey !== '';
    }

    private function request(string $method, string $endpoint, array $data = []): array
    {
        if (! $this->isConfigured()) {
            return ['code' => 0, 'msg' => 'Upstream API not configured'];
        }

        $url = $this->baseUrl.$endpoint;

        try {
            $http = Http::withToken($this->apiKey)
                ->acceptJson()
                ->timeout(30);

            $response = match (strtoupper($method)) {
                'GET' => $http->get($url, $data),
                'POST' => $http->post($url, $data),
                'PUT' => $http->put($url, $data),
                'DELETE' => $http->delete($url, $data),
                default => throw new \InvalidArgumentException("Unsupported HTTP method: $method"),
            };

            $this->logRequest($method, $url, $data, $response->json() ?? [], $response->status(), $response->successful());

            if ($response->successful()) {
                $json = $response->json();

                return $json['code'] === 1 ? $json : ['code' => 0, 'msg' => $json['msg'] ?? 'Request failed'];
            }

            return ['code' => 0, 'msg' => 'Upstream request failed: '.$response->status()];
        } catch (\Exception $e) {
            $this->logRequest($method, $url, $data, ['error' => $e->getMessage()], 0, false);

            return ['code' => 0, 'msg' => $e->getMessage()];
        }
    }

    private function logRequest(string $method, string $url, array $params, array $response, int $statusCode, bool $success): void
    {
        LogBuffer::add(CaLog::class, [
            'url' => $url,
            'api' => $method,
            'params' => $params,
            'response' => $response,
            'status_code' => $statusCode,
            'status' => $success ? 1 : 0,
        ]);
    }
}
