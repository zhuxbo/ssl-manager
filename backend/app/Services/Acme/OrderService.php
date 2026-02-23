<?php

declare(strict_types=1);

namespace App\Services\Acme;

use App\Models\Acme\AcmeAccount;
use App\Models\Acme\AcmeAuthorization;
use App\Models\Cert;
use App\Models\Order;
use App\Models\Transaction;
use App\Services\Delegation\CnameDelegationService;
use App\Services\Delegation\DelegationDnsService;
use App\Services\Order\Utils\OrderUtil;
use App\Services\Order\Utils\ValidatorUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        private BillingService $billingService,
        private AcmeApiClient $apiClient,
        private CnameDelegationService $cnameDelegationService,
        private DelegationDnsService $delegationDnsService
    ) {}

    /**
     * 创建 ACME 订单 — SAN 验证 + 获取真实 challenge
     */
    public function create(AcmeAccount $account, array $identifiers): array
    {
        // 1. 查找有效 Order + Product + 当前 latestCert
        // 优先通过 order_id 精确定位，为空时回落到 user_id 查询（兼容旧数据）
        $user = $account->user;
        $order = null;

        if ($account->order_id) {
            $order = Order::where('id', $account->order_id)
                ->where('period_till', '>', now())
                ->whereNull('cancelled_at')
                ->first();
        }

        if (! $order) {
            $order = Order::where('user_id', $user->id)
                ->whereHas('product', fn ($q) => $q->where('support_acme', 1))
                ->where('period_till', '>', now())
                ->whereNull('cancelled_at')
                ->orderBy('period_till', 'desc')
                ->first();
        }

        if (! $order) {
            $billingCheck = $this->billingService->canIssueCertificate($account);
            if (! $billingCheck['allowed']) {
                return [
                    'error' => $billingCheck['error'],
                    'detail' => $billingCheck['detail'],
                ];
            }
            $order = $billingCheck['order'];
        }

        $product = $order->product;
        $currentLatestCert = $order->latestCert;

        if (! $currentLatestCert || ! $product) {
            return ['error' => 'orderNotReady', 'detail' => 'Order has no valid cert or product'];
        }

        // 2. SAN 验证
        $domains = array_map(fn ($id) => $id['value'], $identifiers);
        $domainsString = implode(',', $domains);

        $sanErrors = ValidatorUtil::validateSansMaxCount($product->toArray(), $domainsString);
        if (! empty($sanErrors)) {
            return ['error' => 'rejectedIdentifier', 'detail' => implode('; ', array_values($sanErrors))];
        }

        // SAN 计数（gift_root_domain 仅影响计费）
        $sans = OrderUtil::getSansFromDomains($domainsString, $product->gift_root_domain);
        $standardCount = $sans['standard_count'];
        $wildcardCount = $sans['wildcard_count'];

        // 3. 判断是否复用已有 pending cert（无 api_id 表示上次失败未到达上游）
        $canReuse = $currentLatestCert->status === 'pending'
            && empty($currentLatestCert->api_id)
            && empty($currentLatestCert->cert);

        if ($canReuse) {
            $cert = $currentLatestCert;
        } else {
            // 新建 cert，更新 order.latest_cert_id
            $cert = Cert::create([
                'order_id' => $order->id,
                'last_cert_id' => $currentLatestCert->id,
                'action' => 'reissue',
                'channel' => 'acme',
                'common_name' => $domains[0] ?? '',
                'email' => $user->email,
                'standard_count' => $standardCount,
                'wildcard_count' => $wildcardCount,
                'status' => 'pending',
            ]);

            $order->latest_cert_id = $cert->id;
            $order->save();
        }

        // 4. 幂等扣费：通过 purchased count 判断是否需要增购
        $needCharge = $standardCount > ($order->purchased_standard_count ?? 0)
            || $wildcardCount > ($order->purchased_wildcard_count ?? 0);

        if ($needCharge) {
            try {
                DB::transaction(function () use ($order, $cert, $product, $user, $standardCount, $wildcardCount) {
                    // 更新 cert 的 SAN 数量以便计算金额
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

                    // 扣费成功，立即更新 purchased 计数
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
                return ['error' => 'orderNotReady', 'detail' => $e->getMessage()];
            }
        }

        // 5. 调用上游 API
        if ($this->apiClient->isConfigured()) {
            $acmeAccountId = $account->acme_account_id;

            // 延迟注册：上游账户不存在时先创建
            if (! $acmeAccountId) {
                $acmeResult = $this->apiClient->createAccount(
                    $user->email ?? '',
                    (string) $product->api_id
                );

                if ($acmeResult['code'] === 1 && isset($acmeResult['data']['id'])) {
                    $acmeAccountId = $acmeResult['data']['id'];
                    $account->update(['acme_account_id' => $acmeAccountId]);
                    $order->update(['acme_account_id' => $acmeAccountId]);
                } else {
                    return ['error' => 'serverInternal', 'detail' => $acmeResult['msg'] ?? 'Failed to create upstream account'];
                }
            }

            $apiResult = $this->apiClient->createOrder(
                $acmeAccountId,
                $domains,
                (string) $product->api_id
            );

            if ($apiResult['code'] !== 1) {
                // 失败 → cert 无 api_id，下次重试步骤 3 会复用它
                return ['error' => 'serverInternal', 'detail' => $apiResult['msg'] ?? 'Upstream order creation failed'];
            }

            $apiData = $apiResult['data'];

            // 6. 成功 → 更新 cert（api_id、域名）
            $cert->update([
                'common_name' => $domains[0] ?? '',
                'alternative_names' => $domainsString,
                'standard_count' => $standardCount,
                'wildcard_count' => $wildcardCount,
                'api_id' => $apiData['id'],
            ]);

            // 7. 创建 AcmeAuthorizations
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

            // 8. 委托写 TXT（best-effort）
            $this->writeDelegationTxtForAuthorizations($cert->fresh('acmeAuthorizations'));

            // 9. 生成标准 dcv/validation 数据
            $this->populateDcvAndValidation($cert->fresh('acmeAuthorizations'));
        } else {
            return ['error' => 'serverInternal', 'detail' => 'Upstream gateway not configured'];
        }

        return ['order' => $cert->fresh('acmeAuthorizations')];
    }

    /**
     * 通过 refer_id 获取订单
     */
    public function get(string $referId): ?Cert
    {
        return Cert::where('refer_id', $referId)
            ->where('channel', 'acme')
            ->with('acmeAuthorizations')
            ->first();
    }

    /**
     * 获取授权
     */
    public function getAuthorization(string $token): ?AcmeAuthorization
    {
        return AcmeAuthorization::where('token', $token)->first();
    }

    /**
     * 响应验证挑战 — 调用连接服务验证
     */
    public function respondToChallenge(AcmeAuthorization $authorization): array
    {
        if ($this->apiClient->isConfigured() && $authorization->acme_challenge_id) {
            try {
                $result = $this->apiClient->respondToChallenge((int) $authorization->acme_challenge_id);

                if ($result['code'] === 1) {
                    $status = $result['data']['status'] ?? 'valid';

                    $authorization->update([
                        'challenge_status' => $status,
                        'challenge_validated' => $status === 'valid' ? now() : null,
                        'status' => $status,
                    ]);

                    return ['status' => $status];
                }

                // "already valid" 情况（匹配 '"valid" state' 避免误匹配 'invalid'）
                if (str_contains($result['msg'] ?? '', '"valid" state')) {
                    $authorization->update([
                        'challenge_status' => 'valid',
                        'challenge_validated' => now(),
                        'status' => 'valid',
                    ]);

                    return ['status' => 'valid'];
                }

                return ['status' => 'pending'];
            } catch (\Exception $e) {
                return ['status' => 'pending'];
            }
        }

        // 无上游时直接标记有效
        $authorization->update([
            'challenge_status' => 'valid',
            'challenge_validated' => now(),
            'status' => 'valid',
        ]);

        return ['status' => 'valid'];
    }

    /**
     * 完成订单 — 调用连接服务签发 + 写入系统 Cert
     */
    public function finalize(Cert $cert, string $csr): array
    {
        $acmeStatus = $this->getAcmeStatus($cert);

        if ($acmeStatus !== 'ready') {
            return [
                'error' => 'orderNotReady',
                'detail' => "Order is not ready for finalization, current status: $acmeStatus",
            ];
        }

        // base64url CSR → PEM
        $csrDer = base64_decode(strtr($csr, '-_', '+/'));
        $csrPem = "-----BEGIN CERTIFICATE REQUEST-----\n"
            .chunk_split(base64_encode($csrDer), 64, "\n")
            .'-----END CERTIFICATE REQUEST-----';

        if (! $this->apiClient->isConfigured() || ! $cert->api_id) {
            return ['error' => 'serverInternal', 'detail' => 'Upstream gateway not configured or no api_id'];
        }

        // 调用连接服务 finalize
        $finalizeResult = $this->apiClient->finalizeOrder((int) $cert->api_id, $csrPem);

        if ($finalizeResult['code'] !== 1) {
            $msg = $finalizeResult['msg'] ?? 'Upstream finalization failed';

            // badCSR 不标记为 failed
            if (! str_contains($msg, 'badCSR')) {
                $cert->update(['status' => 'failed']);
            }

            $errorType = str_contains($msg, 'badCSR') ? 'badCSR' : 'serverInternal';

            return ['error' => $errorType, 'detail' => $msg];
        }

        // Gateway 返回 processing 表示 CA 仍在处理中
        $upstreamStatus = $finalizeResult['data']['status'] ?? '';
        if ($upstreamStatus === 'processing') {
            $cert->csr = $csrPem;
            $cert->csr_md5 = md5($csrPem);
            $cert->save();

            return ['order' => $cert->fresh('acmeAuthorizations')];
        }

        // 获取证书
        $certResult = $this->apiClient->getCertificate((int) $cert->api_id);

        if ($certResult['code'] !== 1) {
            return ['error' => 'serverInternal', 'detail' => 'Failed to retrieve certificate'];
        }

        $this->saveCertificateFromUpstream($cert, $csrPem, $certResult['data']);

        return ['order' => $cert->fresh('acmeAuthorizations')];
    }

    /**
     * 保存上游返回的证书数据到 cert
     */
    private function saveCertificateFromUpstream(Cert $cert, string $csrPem, array $certData): void
    {
        $certificate = $certData['certificate'] ?? '';
        $chain = $certData['chain'] ?? '';

        $certParsed = openssl_x509_parse($certificate);
        $serialNumber = $certParsed['serialNumberHex'] ?? null;
        $issuerCn = $certParsed['issuer']['CN'] ?? null;

        $issuedAt = isset($certParsed['validFrom_time_t'])
            ? \Carbon\Carbon::createFromTimestamp($certParsed['validFrom_time_t'])
            : now();
        $expiresAt = isset($certParsed['validTo_time_t'])
            ? \Carbon\Carbon::createFromTimestamp($certParsed['validTo_time_t'])
            : now()->addMonths(12);

        $encryption = $certParsed['signatureTypeSN'] ?? '';
        $encryption = explode('-', $encryption);
        $pubKeyId = openssl_pkey_get_public($certificate);
        $keyDetails = openssl_pkey_get_details($pubKeyId);

        $cert->csr = $csrPem;
        $cert->csr_md5 = md5($csrPem);
        $cert->cert = $certificate;
        $cert->serial_number = $serialNumber;
        $cert->issued_at = $issuedAt;
        $cert->expires_at = $expiresAt;
        $cert->fingerprint = openssl_x509_fingerprint($certificate) ?: '';
        $cert->encryption_alg = $encryption[0] ?? '';
        $cert->encryption_bits = $keyDetails['bits'] ?? 0;
        $cert->signature_digest_alg = $encryption[1] ?? '';
        $cert->status = 'active';

        if ($issuerCn) {
            $cert->issuer = $issuerCn;
            $cert->intermediate_cert = $chain;
        }

        $cert->save();
    }

    /**
     * 尝试完成挂起的 finalize：向上游查询证书是否已签发
     */
    public function tryCompletePendingFinalize(Cert $cert): bool
    {
        if (! $this->apiClient->isConfigured() || ! $cert->api_id) {
            return false;
        }

        try {
            $certResult = $this->apiClient->getCertificate((int) $cert->api_id);

            if ($certResult['code'] !== 1) {
                return false;
            }

            $this->saveCertificateFromUpstream($cert, $cert->csr, $certResult['data']);

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * 推导 ACME 状态
     */
    public function getAcmeStatus(Cert $cert): string
    {
        if (in_array($cert->status, ['revoked', 'cancelled', 'failed'])) {
            return 'invalid';
        }

        if (! empty($cert->cert)) {
            return 'valid';
        }

        if (! empty($cert->csr)) {
            return 'processing';
        }

        // 检查所有授权是否 valid
        $authorizations = $cert->relationLoaded('acmeAuthorizations')
            ? $cert->acmeAuthorizations
            : $cert->acmeAuthorizations()->get();

        if ($authorizations->isNotEmpty() && $authorizations->every(fn ($a) => $a->status === 'valid')) {
            return 'ready';
        }

        return 'pending';
    }

    /**
     * 生成订单 URL
     */
    public function getOrderUrl(Cert $cert): string
    {
        $baseUrl = rtrim(request()->getSchemeAndHttpHost(), '/');

        return "$baseUrl/acme/order/$cert->refer_id";
    }

    /**
     * 生成 Finalize URL
     */
    public function getFinalizeUrl(Cert $cert): string
    {
        $baseUrl = rtrim(request()->getSchemeAndHttpHost(), '/');

        return "$baseUrl/acme/order/$cert->refer_id/finalize";
    }

    /**
     * 生成证书 URL
     */
    public function getCertificateUrl(Cert $cert): string
    {
        $baseUrl = rtrim(request()->getSchemeAndHttpHost(), '/');

        return "$baseUrl/acme/cert/$cert->refer_id";
    }

    /**
     * 生成授权 URL
     */
    public function getAuthorizationUrl(AcmeAuthorization $authorization): string
    {
        $baseUrl = rtrim(request()->getSchemeAndHttpHost(), '/');

        return "$baseUrl/acme/authz/$authorization->token";
    }

    /**
     * 生成挑战 URL
     */
    public function getChallengeUrl(AcmeAuthorization $authorization): string
    {
        $baseUrl = rtrim(request()->getSchemeAndHttpHost(), '/');

        return "$baseUrl/acme/chall/$authorization->token";
    }

    /**
     * 格式化订单响应（接收 Cert）
     */
    public function formatOrderResponse(Cert $cert): array
    {
        $status = $this->getAcmeStatus($cert);

        $authorizations = $cert->relationLoaded('acmeAuthorizations')
            ? $cert->acmeAuthorizations
            : $cert->acmeAuthorizations()->get();

        $response = [
            'status' => $status,
            'expires' => $cert->expires_at?->utc()->toIso8601ZuluString()
                ?? $authorizations->first()?->expires_at?->utc()->toIso8601ZuluString()
                ?? now()->utc()->addDays(7)->toIso8601ZuluString(),
            'identifiers' => $authorizations->map(fn ($a) => [
                'type' => $a->identifier_type,
                'value' => $a->identifier_value,
            ])->toArray(),
            'authorizations' => $authorizations->map(
                fn ($a) => $this->getAuthorizationUrl($a)
            )->toArray(),
            'finalize' => $this->getFinalizeUrl($cert),
        ];

        if ($status === 'valid') {
            $response['certificate'] = $this->getCertificateUrl($cert);
        }

        return $response;
    }

    /**
     * 格式化授权响应
     */
    public function formatAuthorizationResponse(AcmeAuthorization $authorization): array
    {
        return [
            'identifier' => [
                'type' => $authorization->identifier_type,
                'value' => $authorization->identifier_value,
            ],
            'status' => $authorization->status,
            'expires' => ($authorization->expires_at ?? now()->addDays(7))->utc()->toIso8601ZuluString(),
            'wildcard' => $authorization->wildcard,
            'challenges' => [
                [
                    'type' => $authorization->challenge_type,
                    'status' => $authorization->challenge_status,
                    'url' => $this->getChallengeUrl($authorization),
                    'token' => $authorization->challenge_token,
                ],
            ],
        ];
    }

    /**
     * 格式化挑战响应
     */
    public function formatChallengeResponse(AcmeAuthorization $authorization): array
    {
        $response = [
            'type' => $authorization->challenge_type,
            'status' => $authorization->challenge_status,
            'url' => $this->getChallengeUrl($authorization),
            'token' => $authorization->challenge_token,
        ];

        if ($authorization->challenge_validated) {
            $response['validated'] = $authorization->challenge_validated->utc()->toIso8601ZuluString();
        }

        return $response;
    }

    /**
     * 将 ACME 挑战数据映射为标准 dcv/validation 格式
     *
     * @param  string|null  $method  验证方式：null=自动检测, delegation=委托, txt=直接TXT
     */
    public function populateDcvAndValidation(Cert $cert, ?string $method = null): void
    {
        $authorizations = $cert->relationLoaded('acmeAuthorizations')
            ? $cert->acmeAuthorizations
            : $cert->acmeAuthorizations()->get();

        if ($authorizations->isEmpty()) {
            return;
        }

        $first = $authorizations->first();
        $challengeType = $first->challenge_type ?? 'dns-01';

        if ($challengeType === 'dns-01') {
            // 查找委托记录
            $userId = $cert->order?->user_id;
            $delegations = [];
            if ($userId && $method !== 'txt') {
                foreach ($authorizations as $authz) {
                    $domain = ltrim($authz->identifier_value, '*.');
                    $delegation = $this->cnameDelegationService->findValidDelegation($userId, $domain, '_acme-challenge');
                    if ($delegation) {
                        $delegations[$authz->identifier_value] = $delegation;
                    }
                }
            }

            // 自动检测：有委托记录则默认使用委托模式
            $useDelegate = $method === 'delegation' || ($method === null && ! empty($delegations));

            // 取第一个授权的 TXT 值作为 dcv 默认值
            $firstTxtValue = $first->key_authorization
                ? rtrim(strtr(base64_encode(hash('sha256', $first->key_authorization, true)), '+/', '-_'), '=')
                : '';

            $dcv = [
                'method' => 'txt',
                'dns' => [
                    'host' => '_acme-challenge',
                    'type' => 'TXT',
                    'value' => $firstTxtValue,
                ],
            ];

            if ($useDelegate) {
                $dcv['is_delegate'] = true;
                $dcv['ca'] = strtolower($cert->order?->product?->ca ?? '');
                $dcv['channel'] = 'acme';
            }

            $validation = $authorizations->map(function ($authz) use ($useDelegate, $delegations) {
                $txtValue = $authz->key_authorization
                    ? rtrim(strtr(base64_encode(hash('sha256', $authz->key_authorization, true)), '+/', '-_'), '=')
                    : '';

                $item = [
                    'domain' => $authz->identifier_value,
                    'method' => 'txt',
                    'host' => '_acme-challenge',
                    'value' => $txtValue,
                    'verified' => $authz->status === 'valid' ? 1 : 0,
                ];

                if ($useDelegate) {
                    $delegation = $delegations[$authz->identifier_value] ?? null;
                    if ($delegation) {
                        $item['delegation_id'] = $delegation->id;
                        $item['delegation_target'] = $delegation->target_fqdn;
                        $item['delegation_valid'] = $delegation->valid;
                        $item['delegation_zone'] = $delegation->zone;
                    }
                }

                return $item;
            })->values()->toArray();
        } else {
            // http-01
            $dcv = [
                'method' => 'file',
                'file' => [
                    'name' => $first->challenge_token,
                    'path' => "/.well-known/acme-challenge/$first->challenge_token",
                    'content' => $first->key_authorization,
                ],
            ];

            $validation = $authorizations->map(fn ($authz) => [
                'domain' => $authz->identifier_value,
                'method' => 'file',
                'verified' => $authz->status === 'valid' ? 1 : 0,
            ])->values()->toArray();
        }

        // 计算 domain_verify_status
        $validCount = $authorizations->where('status', 'valid')->count();
        $totalCount = $authorizations->count();
        $domainVerifyStatus = $validCount === $totalCount ? 2 : ($validCount > 0 ? 1 : 0);

        $cert->update([
            'dcv' => $dcv,
            'validation' => $validation,
            'domain_verify_status' => $domainVerifyStatus,
        ]);
    }

    /**
     * ACME 手工验证签发
     */
    public function acmeRevalidate(Cert $cert): void
    {
        $authorizations = $cert->acmeAuthorizations()->where('status', '!=', 'valid')->get();

        foreach ($authorizations as $authorization) {
            $this->respondToChallenge($authorization);
        }

        // 刷新 cert 的 validation 数据
        $cert->refresh();
        $this->populateDcvAndValidation($cert);
    }

    /**
     * ACME 切换/刷新 DCV 验证方式
     */
    public function acmeUpdateDCV(Cert $cert, ?string $method = null): void
    {
        $this->populateDcvAndValidation($cert, $method);
    }

    /**
     * 尝试为 dns-01 授权写入委托 TXT 记录（best-effort）
     */
    public function writeDelegationTxtForAuthorizations(Cert $cert): void
    {
        $authorizations = $cert->relationLoaded('acmeAuthorizations')
            ? $cert->acmeAuthorizations
            : $cert->acmeAuthorizations()->get();

        $userId = $cert->order?->user_id;
        if (! $userId) {
            return;
        }

        foreach ($authorizations as $authorization) {
            if ($authorization->challenge_type !== 'dns-01' || empty($authorization->key_authorization)) {
                continue;
            }

            $domain = $authorization->identifier_value ?? 'unknown';
            try {
                $domain = ltrim($authorization->identifier_value, '*.');
                $delegation = $this->cnameDelegationService->findValidDelegation($userId, $domain, '_acme-challenge');

                if (! $delegation) {
                    continue;
                }

                $txtValue = rtrim(strtr(base64_encode(hash('sha256', $authorization->key_authorization, true)), '+/', '-_'), '=');
                $this->delegationDnsService->setTxtByLabel($delegation->proxy_zone, $delegation->label, [$txtValue]);
            } catch (\Exception $e) {
                Log::warning("ACME 委托写 TXT 失败: domain=$domain, error=".$e->getMessage());
            }
        }
    }
}
