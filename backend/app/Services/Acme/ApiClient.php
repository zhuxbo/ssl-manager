<?php

declare(strict_types=1);

namespace App\Services\Acme;

use App\Models\CaLog;
use App\Services\LogBuffer;
use Illuminate\Support\Facades\Http;

class ApiClient
{
    private string $baseUrl;

    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = $this->resolveBaseUrl();
        $this->apiKey = get_system_setting('ca', 'acmeToken')
            ?? get_system_setting('ca', 'token')
            ?? '';
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

    /**
     * 创建订单（合并原 createAccount + createOrder）
     */
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

    /**
     * 重签订单
     */
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

    /**
     * 获取订单详情
     */
    public function getOrder(int $orderId): array
    {
        return $this->request('GET', "/orders/$orderId");
    }

    /**
     * 获取授权列表
     */
    public function getOrderAuthorizations(int $orderId): array
    {
        return $this->request('GET', "/orders/authorizations/$orderId");
    }

    /**
     * 响应验证挑战
     */
    public function respondToChallenge(int $challengeId): array
    {
        return $this->request('POST', "/challenges/respond/$challengeId");
    }

    /**
     * 完成订单
     */
    public function finalizeOrder(int $orderId, string $csr): array
    {
        return $this->request('POST', "/orders/finalize/$orderId", [
            'csr' => $csr,
        ]);
    }

    /**
     * 获取证书
     */
    public function getCertificate(int $orderId): array
    {
        return $this->request('GET', "/orders/certificate/$orderId");
    }

    /**
     * 取消订单
     */
    public function cancelOrder(int $orderId): array
    {
        return $this->request('DELETE', "/orders/$orderId");
    }

    /**
     * 吊销证书
     */
    public function revokeCertificate(string $serialNumber, string $reason = 'UNSPECIFIED'): array
    {
        return $this->request('POST', '/certificates/revoke', [
            'serial_number' => $serialNumber,
            'reason' => $reason,
        ]);
    }

    /**
     * 是否已配置
     */
    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->apiKey !== '';
    }

    /**
     * 发送 HTTP 请求
     */
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

    /**
     * 记录请求日志
     */
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
