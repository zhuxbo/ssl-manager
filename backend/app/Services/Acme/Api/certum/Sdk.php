<?php

declare(strict_types=1);

namespace App\Services\Acme\Api\certum;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class Sdk
{
    protected string $baseUrl;

    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('acme.api.base_url'), '/');
        $this->apiKey = (string) config('acme.api.api_key');
    }

    /**
     * 创建 ACME 订单
     *
     * POST {base_url}/api/acme/new
     */
    public function new(array $data): array
    {
        return $this->request('POST', '/api/acme/new', $data);
    }

    /**
     * 查询 ACME 订单
     *
     * GET {base_url}/api/acme/get?order_id={id}
     */
    public function get(string|int $id): array
    {
        return $this->request('GET', '/api/acme/get', ['order_id' => $id]);
    }

    /**
     * 取消 ACME 订单
     *
     * POST {base_url}/api/acme/cancel
     */
    public function cancel(string|int $id): array
    {
        return $this->request('POST', '/api/acme/cancel', ['order_id' => $id]);
    }

    /**
     * 同步 ACME 订单
     *
     * POST {base_url}/api/acme/sync
     */
    public function sync(string|int $id): array
    {
        return $this->request('POST', '/api/acme/sync', ['order_id' => $id]);
    }

    /**
     * 获取产品列表
     *
     * GET {base_url}/api/acme/get-products
     */
    public function getProducts(string $brand = '', string $code = ''): array
    {
        $params = array_filter([
            'brand' => $brand,
            'code' => $code,
        ]);

        return $this->request('GET', '/api/acme/get-products', $params);
    }

    /**
     * 发送 HTTP 请求
     */
    protected function request(string $method, string $path, array $data = []): array
    {
        if (! $this->baseUrl || ! $this->apiKey) {
            return ['code' => 0, 'msg' => 'ACME API 未配置'];
        }

        $url = $this->baseUrl.$path;

        try {
            $request = Http::withToken($this->apiKey)
                ->timeout(30)
                ->acceptJson();

            $response = match (strtoupper($method)) {
                'GET' => $request->get($url, $data),
                'POST' => $request->asJson()->post($url, $data),
                default => $request->asJson()->send($method, $url, ['json' => $data]),
            };

            $responseData = $response->json() ?? [];

            if ($response->successful()) {
                if (! isset($responseData['code'])) {
                    return ['code' => 0, 'msg' => '上游返回格式错误'];
                }

                return $responseData;
            }

            return [
                'code' => 0,
                'msg' => $responseData['msg'] ?? 'HTTP '.$response->status(),
                'errors' => $responseData['errors'] ?? [],
            ];
        } catch (ConnectionException $e) {
            return ['code' => 0, 'msg' => '上游连接失败'];
        }
    }
}
