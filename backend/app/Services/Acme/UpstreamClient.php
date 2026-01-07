<?php

declare(strict_types=1);

namespace App\Services\Acme;

use App\Models\CaLog;
use Illuminate\Support\Facades\Http;

class UpstreamClient
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('acme.api.base_url', ''), '/');
        $this->apiKey = config('acme.api.api_key', '');
    }

    /**
     * 创建账户
     */
    public function createAccount(string $customer, int $productCode): array
    {
        return $this->request('POST', '/acme/accounts', [
            'customer' => $customer,
            'product_code' => $productCode,
        ]);
    }

    /**
     * 获取账户信息
     */
    public function getAccount(int $accountId): array
    {
        return $this->request('GET', "/acme/accounts/$accountId");
    }

    /**
     * 创建订单
     */
    public function createOrder(int $accountId, array $domains, string $productCode): array
    {
        return $this->request('POST', '/acme/orders', [
            'account_id' => $accountId,
            'domains' => $domains,
            'product_code' => $productCode,
        ]);
    }

    /**
     * 获取订单详情
     */
    public function getOrder(int $orderId): array
    {
        return $this->request('GET', "/acme/orders/$orderId");
    }

    /**
     * 获取授权列表
     */
    public function getOrderAuthorizations(int $orderId): array
    {
        return $this->request('GET', "/acme/orders/$orderId/authorizations");
    }

    /**
     * 响应验证挑战
     */
    public function respondToChallenge(int $challengeId): array
    {
        return $this->request('POST', "/acme/challenges/$challengeId/respond");
    }

    /**
     * 完成订单
     */
    public function finalizeOrder(int $orderId, string $csr): array
    {
        return $this->request('POST', "/acme/orders/$orderId/finalize", [
            'csr' => $csr,
        ]);
    }

    /**
     * 获取证书
     */
    public function getCertificate(int $orderId): array
    {
        return $this->request('GET', "/acme/orders/$orderId/certificate");
    }

    /**
     * 吊销证书
     */
    public function revokeCertificate(int $certificateId, string $reason = 'UNSPECIFIED'): array
    {
        return $this->request('POST', "/acme/certificates/$certificateId/revoke", [
            'reason' => $reason,
        ]);
    }

    /**
     * 发送 HTTP 请求
     */
    private function request(string $method, string $endpoint, array $data = []): array
    {
        if (!$this->baseUrl || !$this->apiKey) {
            return ['code' => 0, 'msg' => 'Upstream API not configured'];
        }

        $url = $this->baseUrl . $endpoint;

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

            return ['code' => 0, 'msg' => 'Upstream request failed: ' . $response->status()];
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
        CaLog::create([
            'url' => $url,
            'api' => $method,
            'params' => $params,
            'response' => $response,
            'status_code' => $statusCode,
            'status' => $success ? 1 : 0,
        ]);
    }
}
