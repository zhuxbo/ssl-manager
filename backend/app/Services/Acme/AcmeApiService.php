<?php

declare(strict_types=1);

namespace App\Services\Acme;

use App\Models\Acme\AcmeAuthorization;
use App\Models\Cert;
use App\Models\Order;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Order\Utils\OrderUtil;
use App\Services\Order\Utils\ValidatorUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AcmeApiService
{
    public function __construct(
        private AcmeApiClient $apiClient,
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

                // b. 创建 Cert
                $cert = Cert::create([
                    'order_id' => $order->id,
                    'action' => 'new',
                    'channel' => 'acme',
                    'common_name' => '',
                    'email' => $user->email,
                    'standard_count' => $product->standard_min,
                    'wildcard_count' => $product->wildcard_min,
                    'status' => 'unpaid',
                ]);

                // c. 计算金额
                $order->latest_cert_id = $cert->id;
                $order->save();

                $amount = OrderUtil::getLatestCertAmount($order->toArray(), $cert->toArray(), $product->toArray());

                // d. 更新金额
                $cert->amount = $amount;
                $cert->save();

                $order->amount = $amount;
                $order->save();

                // e. 扣费（参考 ActionTrait::charge）
                if (bccomp($amount, '0.00', 2) !== 0) {
                    $transaction = OrderUtil::getOrderTransaction($order->fresh(['latestCert'])->toArray());

                    // 验证余额
                    $balanceAfter = bcadd((string) $user->balance, (string) $transaction['amount'], 2);
                    if (bccomp($balanceAfter, (string) ($user->credit_limit ?? '0'), 2) === -1) {
                        throw new \Exception('Insufficient balance');
                    }

                    // Transaction::create 的 boot 事件自动扣余额
                    Transaction::create($transaction);
                }

                // 更新已购域名数量
                $order->purchased_standard_count = max(
                    $order->purchased_standard_count ?? 0,
                    $cert->standard_count,
                    $product->standard_min
                );
                $order->purchased_wildcard_count = max(
                    $order->purchased_wildcard_count ?? 0,
                    $cert->wildcard_count,
                    $product->wildcard_min
                );
                $order->save();

                // 更新 cert 状态
                $cert->update(['status' => 'pending']);

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

        $sans = OrderUtil::getSansFromDomains($domainsString, $product->gift_root_domain);
        $standardCount = $sans['standard_count'];
        $wildcardCount = $sans['wildcard_count'];

        // 4. 复用或创建 Cert
        $canReuse = $currentLatestCert->status === 'pending'
            && empty($currentLatestCert->api_id)
            && empty($currentLatestCert->cert);

        if ($canReuse) {
            $cert = $currentLatestCert;
        } else {
            $cert = Cert::create([
                'order_id' => $order->id,
                'last_cert_id' => $currentLatestCert->id,
                'action' => 'reissue',
                'channel' => 'acme',
                'common_name' => $domains[0] ?? '',
                'email' => $order->user->email ?? '',
                'standard_count' => $standardCount,
                'wildcard_count' => $wildcardCount,
                'status' => 'pending',
            ]);

            $order->latest_cert_id = $cert->id;
            $order->save();
        }

        // 5. 幂等扣费
        $needCharge = $standardCount > ($order->purchased_standard_count ?? 0)
            || $wildcardCount > ($order->purchased_wildcard_count ?? 0);

        if ($needCharge) {
            try {
                $user = $order->user;
                DB::transaction(function () use ($order, $cert, $product, $user, $standardCount, $wildcardCount) {
                    $cert->update([
                        'standard_count' => $standardCount,
                        'wildcard_count' => $wildcardCount,
                        'status' => 'unpaid',
                    ]);

                    $amount = OrderUtil::getLatestCertAmount($order->fresh(['latestCert'])->toArray());
                    $cert->update(['amount' => $amount]);

                    if (bccomp($amount, '0.00', 2) !== 0) {
                        $transaction = OrderUtil::getOrderTransaction($order->fresh(['latestCert'])->toArray());
                        $balanceAfter = bcadd((string) $user->balance, (string) $transaction['amount'], 2);
                        if (bccomp($balanceAfter, (string) ($user->credit_limit ?? '0'), 2) === -1) {
                            throw new \Exception('Insufficient balance for additional SANs');
                        }
                        Transaction::create($transaction);
                    }

                    $order->purchased_standard_count = max(
                        $order->purchased_standard_count ?? 0,
                        $standardCount,
                        $product->standard_min
                    );
                    $order->purchased_wildcard_count = max(
                        $order->purchased_wildcard_count ?? 0,
                        $wildcardCount,
                        $product->wildcard_min
                    );
                    $order->save();

                    $cert->update(['status' => 'pending']);
                });
            } catch (\Exception $e) {
                return ['code' => 0, 'msg' => $e->getMessage()];
            }
        }

        // 6. 调上游（使用映射后的 ID）
        if ($this->apiClient->isConfigured()) {
            $apiResult = $this->apiClient->createOrder(
                (int) $order->acme_account_id,
                $domains,
                (string) $product->api_id
            );

            if ($apiResult['code'] !== 1) {
                return ['code' => 0, 'msg' => $apiResult['msg'] ?? 'Upstream order creation failed'];
            }

            $apiData = $apiResult['data'];

            // 7. 存映射：cert.api_id = upstream.data.id
            $cert->update([
                'common_name' => $domains[0] ?? '',
                'alternative_names' => $domainsString,
                'standard_count' => $standardCount,
                'wildcard_count' => $wildcardCount,
                'api_id' => $apiData['id'],
            ]);

            // 8. 创建 AcmeAuthorization，存 acme_challenge_id
            if (isset($apiData['authorizations'])) {
                foreach ($apiData['authorizations'] as $authzData) {
                    $identifier = $authzData['identifier'] ?? [];
                    $challenge = $authzData['challenges'][0] ?? [];

                    AcmeAuthorization::create([
                        'cert_id' => $cert->id,
                        'token' => Str::random(32),
                        'identifier_type' => $identifier['type'] ?? 'dns',
                        'identifier_value' => $identifier['value'] ?? '',
                        'wildcard' => str_starts_with($identifier['value'] ?? '', '*.'),
                        'status' => $authzData['status'] ?? 'pending',
                        'expires' => now()->addDays(7),
                        'challenge_type' => $challenge['type'] ?? 'dns-01',
                        'challenge_token' => $challenge['token'] ?? '',
                        'acme_challenge_id' => $challenge['id'] ?? null,
                        'key_authorization' => $challenge['key_authorization'] ?? null,
                        'challenge_status' => $challenge['status'] ?? 'pending',
                    ]);
                }
            }

            // 9. 委托写 TXT（best-effort）
            $this->orderService->writeDelegationTxtForAuthorizations($cert->fresh('acmeAuthorizations'));
        } else {
            return ['code' => 0, 'msg' => 'Upstream gateway not configured'];
        }

        // 9. 返回 formatOrder，id = cert.id
        return [
            'code' => 1,
            'data' => $this->formatOrder($cert->fresh('acmeAuthorizations')),
        ];
    }

    /**
     * 获取订单详情
     */
    public function getOrder(int $orderId): array
    {
        $cert = Cert::where('id', $orderId)
            ->where('channel', 'acme')
            ->with('acmeAuthorizations')
            ->first();

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
        $cert = Cert::where('id', $orderId)
            ->where('channel', 'acme')
            ->with('acmeAuthorizations')
            ->first();

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
        $authorization = AcmeAuthorization::find($challengeId);

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
        $cert = Cert::where('id', $orderId)
            ->where('channel', 'acme')
            ->first();

        if (! $cert) {
            return ['code' => 0, 'msg' => 'Order not found'];
        }

        $result = $this->orderService->finalize($cert, $csr);

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
        $cert = Cert::where('id', $orderId)
            ->where('channel', 'acme')
            ->first();

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
                $cert->update([
                    'cert' => $certData['certificate'] ?? '',
                    'intermediate_cert' => $certData['chain'] ?? '',
                ]);

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
     * 格式化订单数据（接收 Cert）
     */
    private function formatOrder(Cert $cert): array
    {
        $data = [
            'id' => $cert->id,
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
