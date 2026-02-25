<?php

declare(strict_types=1);

namespace App\Services\Acme;

use App\Models\Acme\Account;
use App\Models\Acme\Authorization;
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
        private ApiClient $apiClient,
        private CnameDelegationService $cnameDelegationService,
        private DelegationDnsService $delegationDnsService
    ) {}

    /**
     * 创建 ACME 订单 — SAN 验证 + 获取真实 challenge
     */
    public function create(Account $account, array $identifiers): array
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

        // 3. prepareAndCharge：cert 复用/创建 + 扣费（事务内）
        try {
            $cert = $this->prepareAndCharge($order, $domains);
        } catch (\Exception $e) {
            return ['error' => 'orderNotReady', 'detail' => $e->getMessage()];
        }

        // 4. 调用上游 API
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

            $upstreamResult = $this->submitToUpstream($cert, $domains, $order, $acmeAccountId);

            if (isset($upstreamResult['error'])) {
                return ['error' => 'serverInternal', 'detail' => $upstreamResult['error']];
            }

            $cert = $upstreamResult['cert'];
        } else {
            return ['error' => 'serverInternal', 'detail' => 'Upstream gateway not configured'];
        }

        return ['order' => $cert];
    }

    /**
     * 验证资源归属：cert 所属 order 的 user_id 是否与 account 的 user_id 一致
     */
    public function verifyOwnership(Cert $cert, ?Account $account): bool
    {
        if (! $account) {
            return false;
        }

        $order = $cert->order;
        if (! $order) {
            return false;
        }

        return $order->user_id === $account->user_id;
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
    public function getAuthorization(string $token): ?Authorization
    {
        return Authorization::where('token', $token)->first();
    }

    /**
     * 响应验证挑战 — 调用连接服务验证
     */
    public function respondToChallenge(Authorization $authorization): array
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

        // CSR 域名验证：CSR 中的域名必须是已授权域名的子集
        // RFC 8555: wildcard authz 的 identifier 不带 *. 前缀，CSR 中带 *.
        $csrDomains = $this->extractDomainsFromCsr($csrPem);
        $authorizations = $cert->acmeAuthorizations;

        foreach ($csrDomains as $csrDomain) {
            $csrLower = strtolower($csrDomain);
            $authorized = $authorizations->contains(function ($auth) use ($csrLower) {
                $authDomain = strtolower($auth->identifier_value);
                if ($csrLower === $authDomain) {
                    return true;
                }
                // *.example.com 匹配 authz identifier example.com
                if (str_starts_with($csrLower, '*.') && substr($csrLower, 2) === $authDomain) {
                    return true;
                }

                return false;
            });

            if (! $authorized) {
                return [
                    'error' => 'badCSR',
                    'detail' => "CSR contains unauthorized domain: $csrDomain",
                ];
            }
        }

        if (! $this->apiClient->isConfigured() || ! $cert->api_id) {
            return ['error' => 'serverInternal', 'detail' => 'Upstream gateway not configured or no api_id'];
        }

        // 调用连接服务 finalize
        $finalizeResult = $this->apiClient->finalizeOrder((int) $cert->api_id, $csrPem);

        if ($finalizeResult['code'] !== 1) {
            $msg = $finalizeResult['msg'] ?? 'Upstream finalization failed';
            $retryable = $finalizeResult['retryable'] ?? false;

            // 上游标记 retryable 的错误（badCSR 等）不改状态，允许重试
            if (! $retryable) {
                $cert->update(['status' => 'failed']);
            }

            $errorType = str_contains($msg, 'badCSR') ? 'badCSR' : 'serverInternal';
            $result = ['error' => $errorType, 'detail' => $msg];
            if ($retryable) {
                $result['retryable'] = true;
            }

            return $result;
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
    public function saveCertificateFromUpstream(Cert $cert, string $csrPem, array $certData): void
    {
        $certificate = $certData['certificate'] ?? '';
        $chain = $certData['chain'] ?? '';

        // M8: 空证书防御
        if (empty($certificate)) {
            Log::warning('saveCertificateFromUpstream: empty certificate', ['cert_id' => $cert->id]);

            return;
        }

        $certParsed = openssl_x509_parse($certificate);

        if ($certParsed === false) {
            Log::warning('saveCertificateFromUpstream: failed to parse certificate', ['cert_id' => $cert->id]);

            return;
        }
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
        $keyDetails = $pubKeyId ? openssl_pkey_get_details($pubKeyId) : false;

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
        return url("/acme/order/$cert->refer_id");
    }

    /**
     * 生成 Finalize URL
     */
    public function getFinalizeUrl(Cert $cert): string
    {
        return url("/acme/order/$cert->refer_id/finalize");
    }

    /**
     * 生成证书 URL
     */
    public function getCertificateUrl(Cert $cert): string
    {
        return url("/acme/cert/$cert->refer_id");
    }

    /**
     * 生成授权 URL
     */
    public function getAuthorizationUrl(Authorization $authorization): string
    {
        return url("/acme/authz/$authorization->token");
    }

    /**
     * 生成挑战 URL
     */
    public function getChallengeUrl(Authorization $authorization): string
    {
        return url("/acme/chall/$authorization->token");
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
    public function formatAuthorizationResponse(Authorization $authorization): array
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
    public function formatChallengeResponse(Authorization $authorization): array
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
     * 事务内完成 cert 复用/创建 + 扣费
     * 返回 ['cert' => Cert] 或抛出异常
     */
    public function prepareAndCharge(Order $order, array $domains): Cert
    {
        $product = $order->product;
        $user = $order->user;
        $currentLatestCert = $order->latestCert;

        if (! $currentLatestCert || ! $product) {
            throw new \Exception('Order has no valid cert or product');
        }

        $domainsString = implode(',', $domains);
        $sans = OrderUtil::getSansFromDomains($domainsString, $product->gift_root_domain);
        $standardCount = $sans['standard_count'];
        $wildcardCount = $sans['wildcard_count'];

        // 清理 stuck cert
        if ($currentLatestCert->status === 'processing' && empty($currentLatestCert->cert)) {
            $currentLatestCert->update(['status' => 'failed']);
            $currentLatestCert->refresh();
        }

        return DB::transaction(function () use ($order, $currentLatestCert, $product, $user, $domains, $standardCount, $wildcardCount) {
            $order = Order::lockForUpdate()->find($order->id);

            $hasIssuedCert = Cert::where('order_id', $order->id)
                ->whereNotNull('cert')
                ->where('cert', '!=', '')
                ->exists();
            $isFirstOrder = ! $hasIssuedCert;

            if (! $isFirstOrder && ! $product->reissue) {
                throw new \Exception('Product does not support reissue');
            }

            $canReuse = $currentLatestCert->status === 'pending'
                && empty($currentLatestCert->api_id)
                && empty($currentLatestCert->cert);

            if ($canReuse) {
                $cert = $currentLatestCert;
                $cert->acmeAuthorizations()->delete();

                $action = $isFirstOrder ? 'new' : 'reissue';
                if ($cert->action !== $action) {
                    $cert->update(['action' => $action]);
                }
            } else {
                $action = $isFirstOrder ? 'new' : 'reissue';

                $cert = Cert::create([
                    'order_id' => $order->id,
                    'last_cert_id' => $currentLatestCert->id,
                    'action' => $action,
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

            // 幂等扣费
            $needCharge = $standardCount > ($order->purchased_standard_count ?? 0)
                || $wildcardCount > ($order->purchased_wildcard_count ?? 0);

            if ($needCharge) {
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
            }

            return $cert;
        });
    }

    /**
     * 调上游 + 创建 authorization
     * 返回 Cert（已更新）或 null（失败，错误信息在 $error 中）
     */
    public function submitToUpstream(Cert $cert, array $domains, Order $order, ?int $acmeAccountId): array
    {
        $domainsString = implode(',', $domains);
        $sans = OrderUtil::getSansFromDomains($domainsString, $order->product->gift_root_domain ?? false);

        $apiResult = $this->apiClient->createOrder(
            $acmeAccountId,
            $domains,
            (string) $order->product->api_id
        );

        if ($apiResult['code'] !== 1) {
            return ['error' => $apiResult['msg'] ?? 'Upstream order creation failed'];
        }

        $apiData = $apiResult['data'];

        $cert->update([
            'common_name' => $domains[0] ?? '',
            'alternative_names' => $domainsString,
            'standard_count' => $sans['standard_count'],
            'wildcard_count' => $sans['wildcard_count'],
            'api_id' => $apiData['id'],
            'status' => 'processing',
        ]);

        if (isset($apiData['authorizations'])) {
            foreach ($apiData['authorizations'] as $authzData) {
                $identifier = $authzData['identifier'] ?? [];
                $challenge = $authzData['challenges'][0] ?? [];

                Authorization::create([
                    'cert_id' => $cert->id,
                    'token' => Str::random(32),
                    'identifier_type' => $identifier['type'] ?? 'dns',
                    'identifier_value' => $identifier['value'] ?? '',
                    'wildcard' => str_starts_with($identifier['value'] ?? '', '*.') || in_array('*.'.($identifier['value'] ?? ''), $domains),
                    'status' => $authzData['status'] ?? 'pending',
                    'expires_at' => now()->addDays(7),
                    'challenge_type' => $challenge['type'] ?? 'dns-01',
                    'challenge_token' => $challenge['token'] ?? '',
                    'acme_challenge_id' => $challenge['id'] ?? null,
                    'key_authorization' => $challenge['key_authorization'] ?? null,
                    'challenge_status' => $challenge['status'] ?? 'pending',
                ]);
            }
        }

        $this->writeDelegationTxtForAuthorizations($cert->fresh('acmeAuthorizations'));
        $this->populateDcvAndValidation($cert->fresh('acmeAuthorizations'));

        return ['cert' => $cert->fresh('acmeAuthorizations')];
    }

    /**
     * 从 CSR PEM 中提取域名（CN + SAN DNS 条目）
     */
    private function extractDomainsFromCsr(string $csrPem): array
    {
        $domains = [];

        $output = [];
        exec('echo '.escapeshellarg($csrPem).' | openssl req -noout -text 2>/dev/null', $output);
        $text = implode("\n", $output);

        // 提取 CN
        if (preg_match('/Subject:.*?CN\s*=\s*([^\s,\/]+)/', $text, $matches)) {
            $domains[] = $matches[1];
        }

        // 提取 SAN DNS 条目
        if (preg_match('/DNS:([^\n]+)/', $text, $matches)) {
            $sanLine = $matches[0];
            preg_match_all('/DNS:([^\s,]+)/', $sanLine, $dnsMatches);
            if (! empty($dnsMatches[1])) {
                $domains = array_merge($domains, $dnsMatches[1]);
            }
        }

        return array_unique(array_map('strtolower', $domains));
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
