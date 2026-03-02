<?php

declare(strict_types=1);

namespace App\Services\Acme;

use App\Models\Cert;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Order\Utils\ValidatorUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApiService
{
    public function __construct(
        private ApiClient $apiClient,
        private OrderService $orderService
    ) {}

    /**
     * 创建订单 — 查找/创建 Order + 扣费 + 调上游新建
     */
    public function createOrder(string $customer, string $productCode, array $domains, ?string $referId = null): array
    {
        $user = User::where('email', $customer)->first();
        if (! $user) {
            return ['code' => 0, 'msg' => 'User not found'];
        }

        $product = Product::where('api_id', $productCode)
            ->where('support_acme', 1)
            ->first();
        if (! $product) {
            return ['code' => 0, 'msg' => 'Product not found'];
        }

        // SAN 验证
        $domainsString = implode(',', $domains);
        $sanErrors = ValidatorUtil::validateSansMaxCount($product->toArray(), $domainsString);
        if (! empty($sanErrors)) {
            return ['code' => 0, 'msg' => implode('; ', array_values($sanErrors))];
        }

        // 复用已有 Order 或创建新 Order
        $order = $this->findOrCreateOrder($user, $product, $referId);

        // prepareAndCharge：cert 复用/创建 + 扣费
        try {
            $cert = $this->orderService->prepareAndCharge($order, $domains);
        } catch (\Exception $e) {
            return ['code' => 0, 'msg' => $e->getMessage()];
        }

        // 调上游（始终新建，不判断）
        if (! $this->apiClient->isConfigured()) {
            return ['code' => 0, 'msg' => 'Upstream gateway not configured'];
        }

        $upstreamResult = $this->orderService->submitNewOrder($cert, $domains, $order);

        if (isset($upstreamResult['error'])) {
            return ['code' => 0, 'msg' => $upstreamResult['error']];
        }

        return [
            'code' => 1,
            'data' => $this->formatOrder($upstreamResult['cert']),
        ];
    }

    /**
     * 重签订单 — 在已有订单上创建新 Cert + 调上游重签
     */
    public function reissueOrder(int $orderId, array $domains, ?string $referId = null): array
    {
        $order = Order::find($orderId);
        if (! $order) {
            return ['code' => 0, 'msg' => 'Order not found'];
        }

        $product = $order->product;
        if (! $product) {
            return ['code' => 0, 'msg' => 'Product not found'];
        }

        // SAN 验证
        $domainsString = implode(',', $domains);
        $sanErrors = ValidatorUtil::validateSansMaxCount($product->toArray(), $domainsString);
        if (! empty($sanErrors)) {
            return ['code' => 0, 'msg' => implode('; ', array_values($sanErrors))];
        }

        // 在 prepareAndCharge 之前保存上游 ID（prepareAndCharge 可能创建新 cert 导致 latestCert 变化）
        $upstreamOrderId = $order->latestCert?->api_id;

        // prepareAndCharge
        try {
            $cert = $this->orderService->prepareAndCharge($order, $domains);
        } catch (\Exception $e) {
            return ['code' => 0, 'msg' => $e->getMessage()];
        }
        if (! $upstreamOrderId) {
            return ['code' => 0, 'msg' => 'No upstream order ID found'];
        }

        if (! $this->apiClient->isConfigured()) {
            return ['code' => 0, 'msg' => 'Upstream gateway not configured'];
        }

        // 调上游（始终重签，不判断）
        $upstreamResult = $this->orderService->submitReissue($cert, $domains, $order, (int) $upstreamOrderId);

        if (isset($upstreamResult['error'])) {
            return ['code' => 0, 'msg' => $upstreamResult['error']];
        }

        return [
            'code' => 1,
            'data' => $this->formatOrder($upstreamResult['cert']),
        ];
    }

    /**
     * 取消订单
     */
    public function cancelOrder(int $orderId): array
    {
        $cert = $this->findCertByOrderId($orderId);

        if (! $cert) {
            return ['code' => 0, 'msg' => 'Order not found'];
        }

        $order = Order::find($cert->order_id);
        if (! $order) {
            return ['code' => 0, 'msg' => 'Order not found'];
        }

        app(BillingService::class)->executeCancel($order);

        return ['code' => 1];
    }

    /**
     * 获取订单详情
     */
    public function getOrder(int $orderId): array
    {
        $cert = $this->findCertByOrderId($orderId);
        if ($cert) {
            $cert->load('acmeAuthorizations');
        }

        if (! $cert) {
            return ['code' => 0, 'msg' => 'Order not found'];
        }

        return [
            'code' => 1,
            'data' => $this->formatOrder($cert),
        ];
    }

    /**
     * 获取订单授权列表
     */
    public function getOrderAuthorizations(int $orderId): array
    {
        $cert = $this->findCertByOrderId($orderId);
        if ($cert) {
            $cert->load('acmeAuthorizations');
        }

        if (! $cert) {
            return ['code' => 0, 'msg' => 'Order not found'];
        }

        $authorizations = $cert->acmeAuthorizations->map(fn ($a) => [
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
                    'key_authorization' => $a->key_authorization,
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
        $authorization = \App\Models\Acme\Authorization::find($challengeId);

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
        $cert = $this->findCertByOrderId($orderId);

        if (! $cert) {
            return ['code' => 0, 'msg' => 'Order not found'];
        }

        $result = $this->orderService->finalize($cert, $csr);

        if (isset($result['error'])) {
            $response = ['code' => 0, 'msg' => $result['detail']];
            if (! empty($result['retryable'])) {
                $response['retryable'] = true;
            }

            return $response;
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
        $cert = $this->findCertByOrderId($orderId);

        if (! $cert) {
            return ['code' => 0, 'msg' => 'Order not found'];
        }

        // 本地有证书 → 直接返回
        if ($cert->cert) {
            return [
                'code' => 1,
                'data' => [
                    'certificate' => $cert->cert,
                    'chain' => $cert->intermediate_cert,
                ],
            ];
        }

        // 本地无证书 + 有上游 + 有 api_id → 从上游获取并存储
        if ($this->apiClient->isConfigured() && $cert->api_id) {
            $result = $this->apiClient->getCertificate((int) $cert->api_id);
            if ($result['code'] === 1) {
                $certData = $result['data'];
                $this->orderService->saveCertificateFromUpstream($cert, $cert->csr ?? '', $certData);

                return [
                    'code' => 1,
                    'data' => [
                        'certificate' => $certData['certificate'] ?? '',
                        'chain' => $certData['chain'] ?? '',
                    ],
                ];
            }
        }

        return ['code' => 0, 'msg' => 'Certificate not ready'];
    }

    /**
     * 吊销证书
     */
    public function revokeCertificate(string $serialNumber, string $reason = 'UNSPECIFIED'): array
    {
        $cert = Cert::where('serial_number', $serialNumber)
            ->where('channel', 'acme')
            ->first();

        if (! $cert) {
            return ['code' => 0, 'msg' => 'Certificate not found'];
        }

        if ($cert->status === 'revoked') {
            return ['code' => 1];
        }

        // 有上游则转发
        if ($this->apiClient->isConfigured()) {
            $result = $this->apiClient->revokeCertificate($serialNumber, $reason);
            if ($result['code'] !== 1) {
                return $result;
            }
        }

        $cert->update(['status' => 'revoked']);

        return ['code' => 1];
    }

    /**
     * 查找或创建 Order（复用已有 ACME 订阅或创建新订阅）
     */
    private function findOrCreateOrder(User $user, Product $product, ?string $referId = null): Order
    {
        // 复用已有 Order：同产品、未过期、未取消
        $existingOrder = Order::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->where('period_till', '>', now())
            ->whereNull('cancelled_at')
            ->orderBy('period_till', 'desc')
            ->first();

        if ($existingOrder) {
            return $existingOrder;
        }

        // 创建新 Order
        return DB::transaction(function () use ($user, $product, $referId) {
            $period = $product->periods[0] ?? 12;

            $order = Order::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'brand' => $product->brand,
                'period' => $period,
                'amount' => 0,
                'period_from' => now(),
                'period_till' => now()->addMonths($period),
                'auto_renew' => true,
            ]);

            $cert = Cert::create([
                'order_id' => $order->id,
                'action' => 'new',
                'channel' => 'acme',
                'common_name' => '',
                'refer_id' => $referId ?? str_replace('-', '', Str::uuid()->toString()),
                'email' => $user->email,
                'standard_count' => $product->standard_min,
                'wildcard_count' => $product->wildcard_min,
                'amount' => '0.00',
                'status' => 'pending',
            ]);

            $order->latest_cert_id = $cert->id;
            $order->amount = '0.00';
            $order->purchased_standard_count = 0;
            $order->purchased_wildcard_count = 0;
            $order->save();

            return $order;
        });
    }

    /**
     * 通过 order.id 查找最新的 ACME cert
     */
    private function findCertByOrderId(int $orderId): ?Cert
    {
        $order = Order::find($orderId);
        if (! $order || ! $order->latest_cert_id) {
            return null;
        }

        return Cert::where('id', $order->latest_cert_id)
            ->where('channel', 'acme')
            ->first();
    }

    /**
     * 格式化订单数据
     */
    private function formatOrder(Cert $cert): array
    {
        $data = [
            'id' => $cert->order_id,
            'cert_id' => $cert->id,
            'identifiers' => [],
            'status' => $this->orderService->getAcmeStatus($cert),
            'created_at' => $cert->created_at->toIso8601String(),
        ];

        if ($cert->relationLoaded('acmeAuthorizations')) {
            $data['authorizations'] = $cert->acmeAuthorizations->map(fn ($a) => [
                'id' => $a->id,
                'identifier' => [
                    'type' => $a->identifier_type,
                    'value' => $a->identifier_value,
                ],
                'status' => $a->status,
                'challenges' => [
                    [
                        'id' => $a->id,
                        'type' => $a->challenge_type,
                        'token' => $a->challenge_token,
                        'key_authorization' => $a->key_authorization,
                        'status' => $a->challenge_status,
                    ],
                ],
            ]);

            $data['identifiers'] = $cert->acmeAuthorizations->map(fn ($a) => [
                'type' => $a->identifier_type,
                'value' => $a->identifier_value,
            ])->toArray();
        }

        if ($cert->cert) {
            $data['certificate'] = [
                'available' => true,
            ];
        }

        return $data;
    }
}
