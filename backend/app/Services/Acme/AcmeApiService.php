<?php

declare(strict_types=1);

namespace App\Services\Acme;

use App\Models\Acme\AcmeAccount;
use App\Models\Acme\AcmeOrder;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;

class AcmeApiService
{
    public function __construct(
        private UpstreamClient $upstreamClient,
        private OrderService $orderService
    ) {}

    /**
     * 创建账户
     */
    public function createAccount(string $customer, int $productCode): array
    {
        // 如果有上游，调用上游
        if (config('acme.api.base_url')) {
            return $this->upstreamClient->createAccount($customer, $productCode);
        }

        // 本地处理：查找或创建用户、创建订单、生成 EAB
        $user = User::where('email', $customer)->first();

        if (! $user) {
            return ['code' => 0, 'msg' => 'User not found'];
        }

        $product = Product::where('api_id', $productCode)
            ->where('product_type', 'acme')
            ->first();

        if (! $product) {
            return ['code' => 0, 'msg' => 'Product not found'];
        }

        // 生成 EAB（使用 base64url 编码，符合 ACME 协议）
        $eabKid = Str::uuid()->toString();
        $eabHmac = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        // 创建订单
        $snowflake = app(\Godruoyi\Snowflake\Snowflake::class);

        $order = Order::create([
            'id' => $snowflake->id(),
            'user_id' => $user->id,
            'product_id' => $product->id,
            'brand' => $product->brand,
            'period' => 12,
            'amount' => 0,
            'period_from' => now(),
            'period_till' => now()->addMonths(12),
            'eab_kid' => $eabKid,
            'eab_hmac' => $eabHmac,
            'auto_renew' => true,
        ]);

        return [
            'code' => 1,
            'data' => [
                'id' => $order->id,
                'email' => $customer,
                'customer_id' => $user->id,
                'eab_kid' => $eabKid,
                'eab_hmac' => $eabHmac,
                'status' => 'valid',
            ],
        ];
    }

    /**
     * 获取账户信息
     */
    public function getAccount(int $accountId): array
    {
        if (config('acme.api.base_url')) {
            return $this->upstreamClient->getAccount($accountId);
        }

        $account = AcmeAccount::find($accountId);

        if (! $account) {
            return ['code' => 0, 'msg' => 'Account not found'];
        }

        return [
            'code' => 1,
            'data' => [
                'id' => $account->id,
                'user_id' => $account->user_id,
                'status' => $account->status,
                'created_at' => $account->created_at->toIso8601String(),
            ],
        ];
    }

    /**
     * 创建订单
     */
    public function createOrder(int $accountId, array $domains, string $productCode): array
    {
        if (config('acme.api.base_url')) {
            return $this->upstreamClient->createOrder($accountId, $domains, $productCode);
        }

        $account = AcmeAccount::find($accountId);

        if (! $account) {
            return ['code' => 0, 'msg' => 'Account not found'];
        }

        $identifiers = array_map(fn ($d) => ['type' => 'dns', 'value' => $d], $domains);

        $result = $this->orderService->create($account, $identifiers);

        if (isset($result['error'])) {
            return ['code' => 0, 'msg' => $result['detail']];
        }

        return [
            'code' => 1,
            'data' => $this->formatOrder($result['order']),
        ];
    }

    /**
     * 获取订单详情
     */
    public function getOrder(int $orderId): array
    {
        if (config('acme.api.base_url')) {
            return $this->upstreamClient->getOrder($orderId);
        }

        $order = $this->orderService->getById($orderId);

        if (! $order) {
            return ['code' => 0, 'msg' => 'Order not found'];
        }

        return [
            'code' => 1,
            'data' => $this->formatOrder($order),
        ];
    }

    /**
     * 获取订单授权列表
     */
    public function getOrderAuthorizations(int $orderId): array
    {
        if (config('acme.api.base_url')) {
            return $this->upstreamClient->getOrderAuthorizations($orderId);
        }

        $order = AcmeOrder::with('authorizations')->find($orderId);

        if (! $order) {
            return ['code' => 0, 'msg' => 'Order not found'];
        }

        $authorizations = $order->authorizations->map(fn ($a) => [
            'id' => $a->id,
            'identifier' => [
                'type' => $a->identifier_type,
                'value' => $a->identifier_value,
            ],
            'wildcard' => $a->wildcard,
            'status' => $a->status,
            'challenges' => [
                [
                    'id' => $a->id,
                    'type' => $a->challenge_type,
                    'token' => $a->challenge_token,
                    'status' => $a->challenge_status,
                ],
            ],
        ]);

        return ['code' => 1, 'data' => $authorizations];
    }

    /**
     * 响应验证挑战
     */
    public function respondToChallenge(int $challengeId): array
    {
        if (config('acme.api.base_url')) {
            return $this->upstreamClient->respondToChallenge($challengeId);
        }

        $authorization = $this->orderService->getAuthorization((string) $challengeId);

        if (! $authorization) {
            return ['code' => 0, 'msg' => 'Challenge not found'];
        }

        $result = $this->orderService->respondToChallenge($authorization);

        return [
            'code' => 1,
            'data' => ['status' => $result['status']],
        ];
    }

    /**
     * 完成订单
     */
    public function finalizeOrder(int $orderId, string $csr): array
    {
        if (config('acme.api.base_url')) {
            return $this->upstreamClient->finalizeOrder($orderId, $csr);
        }

        $order = $this->orderService->getById($orderId);

        if (! $order) {
            return ['code' => 0, 'msg' => 'Order not found'];
        }

        $result = $this->orderService->finalize($order, $csr);

        if (isset($result['error'])) {
            return ['code' => 0, 'msg' => $result['detail']];
        }

        return [
            'code' => 1,
            'data' => $this->formatOrder($result['order']),
        ];
    }

    /**
     * 获取证书
     */
    public function getCertificate(int $orderId): array
    {
        if (config('acme.api.base_url')) {
            return $this->upstreamClient->getCertificate($orderId);
        }

        $order = $this->orderService->getById($orderId);

        if (! $order) {
            return ['code' => 0, 'msg' => 'Order not found'];
        }

        if ($order->status !== 'valid' || ! $order->certificate) {
            return ['code' => 0, 'msg' => 'Certificate not ready'];
        }

        return [
            'code' => 1,
            'data' => [
                'certificate' => $order->certificate,
                'chain' => $order->chain,
            ],
        ];
    }

    /**
     * 吊销证书
     */
    public function revokeCertificate(int $certificateId, string $reason = 'UNSPECIFIED'): array
    {
        if (config('acme.api.base_url')) {
            return $this->upstreamClient->revokeCertificate($certificateId, $reason);
        }

        // TODO: 实现本地吊销逻辑
        return ['code' => 1];
    }

    /**
     * 格式化订单数据
     */
    private function formatOrder(AcmeOrder $order): array
    {
        $data = [
            'id' => $order->id,
            'account_id' => $order->acme_account_id,
            'identifiers' => $order->identifiers,
            'status' => $order->status,
            'expires_at' => $order->expires?->toIso8601String(),
            'created_at' => $order->created_at->toIso8601String(),
        ];

        if ($order->relationLoaded('authorizations')) {
            $data['authorizations'] = $order->authorizations->map(fn ($a) => [
                'id' => $a->id,
                'identifier' => [
                    'type' => $a->identifier_type,
                    'value' => $a->identifier_value,
                ],
                'status' => $a->status,
            ]);
        }

        if ($order->certificate) {
            $data['certificate'] = [
                'available' => true,
            ];
        }

        return $data;
    }
}
