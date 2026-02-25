<?php

declare(strict_types=1);

namespace App\Services\Acme;

use App\Models\Cert;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Order\Utils\ValidatorUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ApiService
{
    public function __construct(
        private ApiClient $apiClient,
        private OrderService $orderService
    ) {}

    /**
     * 创建账户 — 复用已有 Order 或标准扣费流程
     */
    public function createAccount(string $customer, string $productCode): array
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

        // 复用已有 Order：查找用户的有效 ACME Order（同产品、未过期、未取消）
        $existingOrder = Order::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->where('period_till', '>', now())
            ->whereNull('cancelled_at')
            ->whereNotNull('eab_kid')
            ->orderBy('period_till', 'desc')
            ->first();

        if ($existingOrder) {
            // EAB 可复用，统一返回现有 EAB（无需区分已用/未用）
            return [
                'code' => 1,
                'data' => [
                    'id' => $existingOrder->id,
                    'email' => $customer,
                    'customer_id' => $user->id,
                    'eab_kid' => $existingOrder->eab_kid,
                    'eab_hmac' => $existingOrder->eab_hmac,
                    'status' => 'valid',
                ],
            ];
        }

        // 无有效 Order，走原有创建 + 扣费流程
        try {
            $result = DB::transaction(function () use ($user, $product) {
                $period = $product->periods[0] ?? 12;

                // a. 创建 Order
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

                // b. 创建 Cert（不扣费，推迟到 new-order 提交域名后）
                $cert = Cert::create([
                    'order_id' => $order->id,
                    'action' => 'new',
                    'channel' => 'acme',
                    'common_name' => '',
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

                // f. 生成 EAB
                $eabKid = Str::uuid()->toString();
                $eabHmac = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

                // g. 写入 order EAB
                $order->eab_kid = $eabKid;
                $order->eab_hmac = $eabHmac;
                $order->save();

                return [
                    'order' => $order,
                    'cert' => $cert,
                    'eab_kid' => $eabKid,
                    'eab_hmac' => $eabHmac,
                ];
            });

            // 如配置了 acmeApiUrl：调用连接的服务创建账户（best-effort，不阻断本地流程）
            if ($this->apiClient->isConfigured()) {
                try {
                    $upstreamResult = $this->apiClient->createAccount($customer, $productCode);
                    if ($upstreamResult['code'] === 1 && isset($upstreamResult['data']['id'])) {
                        $result['order']->update(['acme_account_id' => $upstreamResult['data']['id']]);
                    } else {
                        Log::warning('createAccount upstream failed', ['msg' => $upstreamResult['msg'] ?? '']);
                    }
                } catch (\Exception $e) {
                    Log::warning('createAccount upstream exception', ['error' => $e->getMessage()]);
                }
            }

            return [
                'code' => 1,
                'data' => [
                    'id' => $result['order']->id,
                    'email' => $customer,
                    'customer_id' => $user->id,
                    'eab_kid' => $result['eab_kid'],
                    'eab_hmac' => $result['eab_hmac'],
                    'status' => 'valid',
                ],
            ];
        } catch (\Exception $e) {
            return ['code' => 0, 'msg' => $e->getMessage()];
        }
    }

    /**
     * 获取账户信息
     */
    public function getAccount(int $accountId): array
    {
        $order = Order::find($accountId);

        if (! $order) {
            return ['code' => 0, 'msg' => 'Account not found'];
        }

        return [
            'code' => 1,
            'data' => [
                'id' => $order->id,
                'email' => $order->user->email ?? '',
                'status' => 'valid',
                'created_at' => $order->created_at->toIso8601String(),
            ],
        ];
    }

    /**
     * 创建订单 — SAN 验证 + 扣费 + 调上游 + 存映射
     */
    public function createOrder(int $accountId, array $domains, string $productCode): array
    {
        // 1. 查找本级 Order
        $order = Order::find($accountId);
        if (! $order) {
            return ['code' => 0, 'msg' => 'Account not found'];
        }

        // 2. 获取 product + latestCert
        $product = $order->product;
        $currentLatestCert = $order->latestCert;

        if (! $product || ! $currentLatestCert) {
            return ['code' => 0, 'msg' => 'Order has no valid cert or product'];
        }

        // 3. SAN 验证
        $domainsString = implode(',', $domains);
        $sanErrors = ValidatorUtil::validateSansMaxCount($product->toArray(), $domainsString);
        if (! empty($sanErrors)) {
            return ['code' => 0, 'msg' => implode('; ', array_values($sanErrors))];
        }

        // 4. prepareAndCharge：cert 复用/创建 + 扣费（事务内）
        try {
            $cert = $this->orderService->prepareAndCharge($order, $domains);
        } catch (\Exception $e) {
            return ['code' => 0, 'msg' => $e->getMessage()];
        }

        // 5. 调上游（使用映射后的 ID）
        if ($this->apiClient->isConfigured()) {
            $upstreamResult = $this->orderService->submitToUpstream(
                $cert,
                $domains,
                $order,
                (int) $order->acme_account_id
            );

            if (isset($upstreamResult['error'])) {
                return ['code' => 0, 'msg' => $upstreamResult['error']];
            }

            $cert = $upstreamResult['cert'];
        } else {
            return ['code' => 0, 'msg' => 'Upstream gateway not configured'];
        }

        return [
            'code' => 1,
            'data' => $this->formatOrder($cert),
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

        // 如果有 api_id 且上游已配置，转发取消请求（best-effort）
        if ($cert->api_id && $this->apiClient->isConfigured()) {
            try {
                $this->apiClient->cancelOrder((int) $cert->api_id);
            } catch (\Exception $e) {
                // best-effort，不阻断本地清理
            }
        }

        // 标记 cert 为 cancelled
        $cert->update(['status' => 'cancelled']);

        // 清理 acme_authorizations
        $cert->acmeAuthorizations()->delete();

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
        $authorization = Authorization::find($challengeId);

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
     * 格式化订单数据（接收 Cert）
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
