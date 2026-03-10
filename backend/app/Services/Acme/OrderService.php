<?php

declare(strict_types=1);

namespace App\Services\Acme;

use App\Models\Acme\Account;
use App\Models\Acme\Authorization;
use App\Models\Cert;
use App\Models\CnameDelegation;
use App\Models\Order;
use App\Models\Transaction;
use App\Services\Delegation\CnameDelegationService;
use App\Services\Delegation\DelegationDnsService;
use App\Services\Order\Utils\DomainUtil;
use App\Services\Order\Utils\OrderUtil;
use App\Services\Order\Utils\ValidatorUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        private CnameDelegationService $cnameDelegationService,
        private DelegationDnsService $delegationDnsService
    ) {}

    /**
     * 创建 ACME 订单 — SAN 验证 + 两步提交上游
     *
     * 新建流程：prepareCert → prepareOrder(获取 api_id) → chargeCert → submitDomains → 保存
     * 重签流程：prepareCert → chargeCert → submitReissue → 保存
     */
    public function create(Account $account, array $identifiers): array
    {
        // 1. 查找有效 Order + Product + 当前 latestCert
        if (! $account->order_id) {
            return ['error' => 'orderNotReady', 'detail' => 'Account has no associated order'];
        }

        $order = Order::where('id', $account->order_id)
            ->where('period_till', '>', now())
            ->whereNull('cancelled_at')
            ->first();

        if (! $order) {
            return ['error' => 'orderNotReady', 'detail' => 'Associated order not found or expired'];
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

        // 3. 幂等检查：cert 已提交上游（processing + api_id）且未签发
        if ($currentLatestCert->channel === 'acme'
            && $currentLatestCert->status === 'processing'
            && $currentLatestCert->api_id
            && empty($currentLatestCert->cert)) {

            // 已有 authorizations → 直接返回
            if ($currentLatestCert->acmeAuthorizations()->exists()) {
                return ['order' => $currentLatestCert->fresh('acmeAuthorizations')];
            }

            // 有 api_id 但无 authorizations → 重试 submitDomains
            $result = $this->submitUpstreamDomains($currentLatestCert, $domains, $order);

            if (isset($result['error'])) {
                return ['error' => 'serverInternal', 'detail' => $result['error']];
            }

            return isset($result['cert']) ? ['order' => $result['cert']] : ['error' => 'serverInternal', 'detail' => 'Failed to save result'];
        }

        // 4. prepareCert：cert 复用/创建（不扣费）
        try {
            $cert = $this->prepareCert($order, $domains);
        } catch (\Exception $e) {
            return ['error' => 'orderNotReady', 'detail' => $e->getMessage()];
        }

        // 5. 调用上游 API
        $upstreamOrderId = $currentLatestCert?->api_id;

        if ($cert->action === 'reissue' && $upstreamOrderId) {
            // 重签：先扣费，再调上游 reissue
            try {
                $this->chargeCert($cert, $order);
            } catch (\Exception $e) {
                return ['error' => 'orderNotReady', 'detail' => $e->getMessage()];
            }
            $upstreamResult = $this->submitReissue($cert, $domains, $order, (int) $upstreamOrderId);
        } else {
            // 新建：submitNewOrder 内部执行 chargeCert → prepareUpstreamOrder → submitUpstreamDomains
            $upstreamResult = $this->submitNewOrder($cert, $domains, $order);
        }

        if (isset($upstreamResult['error'])) {
            return ['error' => 'serverInternal', 'detail' => $upstreamResult['error']];
        }

        return ['order' => $upstreamResult['cert']];
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
        $source = $authorization->cert?->order?->product?->source ?? '';
        $sourceApi = app(Api\Api::class)->getSourceApi($source);

        if ($sourceApi->isConfigured() && $authorization->acme_challenge_id) {
            try {
                $result = $sourceApi->respondToChallenge((int) $authorization->acme_challenge_id);

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

        $source = $cert->order?->product?->source ?? '';
        $sourceApi = app(Api\Api::class)->getSourceApi($source);

        if (! $sourceApi->isConfigured() || ! $cert->api_id) {
            return ['error' => 'serverInternal', 'detail' => 'Upstream not configured or no api_id'];
        }

        // 调用连接服务 finalize
        $finalizeResult = $sourceApi->finalizeOrder((int) $cert->api_id, $csrPem);

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

        // 上游返回 processing 表示 CA 仍在处理中
        $upstreamStatus = $finalizeResult['data']['status'] ?? '';
        if ($upstreamStatus === 'processing') {
            $cert->csr = $csrPem;
            $cert->csr_md5 = md5($csrPem);
            $cert->save();

            return ['order' => $cert->fresh('acmeAuthorizations')];
        }

        // 获取证书
        $certResult = $sourceApi->getCertificate((int) $cert->api_id);

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

        // 证书时间：使用原始时间戳，Eloquent datetime cast 会自动转换为系统时区（参考传统 API parseCert）
        $issuedAt = $certParsed['validFrom_time_t'] ?? 0;
        $expiresAt = $certParsed['validTo_time_t'] ?? 0;

        $encryption = $certParsed['signatureTypeSN'] ?? '';
        $encryption = explode('-', $encryption);
        $pubKeyId = openssl_pkey_get_public($certificate);
        $keyDetails = $pubKeyId ? openssl_pkey_get_details($pubKeyId) : false;

        $cert->csr = $csrPem;
        $cert->csr_md5 = md5($csrPem);
        $cert->cert = $certificate;
        $cert->serial_number = $serialNumber;
        $cert->issued_at = $issuedAt ?: now();
        $cert->expires_at = $expiresAt ?: now()->addMonths(12);
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
        $source = $cert->order?->product?->source ?? '';
        $sourceApi = app(Api\Api::class)->getSourceApi($source);

        if (! $sourceApi->isConfigured() || ! $cert->api_id) {
            return false;
        }

        try {
            $certResult = $sourceApi->getCertificate((int) $cert->api_id);

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
        if (in_array($cert->status, ['revoked', 'revoking', 'cancelled', 'cancelling', 'failed'])) {
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

                    if ($method === 'delegation') {
                        // 用户主动选择委托：查找所有记录（含未验证的），让前端显示 CNAME 目标
                        $delegation = CnameDelegation::where([
                            'user_id' => $userId,
                            'zone' => strtolower(DomainUtil::convertToUnicode($domain)),
                            'prefix' => '_acme-challenge',
                        ])->first();
                    } else {
                        // 自动检测：仅查找已验证的委托记录
                        $delegation = $this->cnameDelegationService->findValidDelegation($userId, $domain, '_acme-challenge');
                    }

                    if ($delegation) {
                        $delegations[$authz->identifier_value] = $delegation;
                    }
                }
            }

            // 有实际委托记录时才启用委托模式（无记录时即使用户选择 delegation 也回退到 txt）
            $useDelegate = ! empty($delegations) && ($method === null || $method === 'delegation');

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
        // 先写委托 TXT（用户可能在订单创建后才配置委托，确保挑战响应前 TXT 已写入）
        $this->writeDelegationTxtForAuthorizations($cert);

        $authorizations = $cert->acmeAuthorizations()->where('status', '!=', 'valid')->get();

        foreach ($authorizations as $authorization) {
            $this->respondToChallenge($authorization);
        }

        // 更新申请状态，保留当前验证方式
        $method = ($cert->dcv['is_delegate'] ?? false) ? 'delegation' : null;
        $cert->refresh();
        $cert->updateQuietly(['cert_apply_status' => 2]);
        $this->populateDcvAndValidation($cert, $method);
    }

    /**
     * ACME 切换/刷新 DCV 验证方式
     */
    public function acmeUpdateDCV(Cert $cert, ?string $method = null): void
    {
        // 切换到委托验证时，自动创建缺失的委托记录
        if ($method === 'delegation') {
            $userId = $cert->order?->user_id;
            if ($userId) {
                $authorizations = $cert->acmeAuthorizations()->where('challenge_type', 'dns-01')->get();
                foreach ($authorizations as $authz) {
                    $domain = ltrim($authz->identifier_value, '*.');
                    $this->cnameDelegationService->createOrGet($userId, $domain, '_acme-challenge');
                }
            }
        }

        $this->populateDcvAndValidation($cert, $method);
    }

    /**
     * cert 复用/创建（不扣费）
     */
    public function prepareCert(Order $order, array $domains): Cert
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

        return DB::transaction(function () use ($order, $currentLatestCert, $product, $user, $domains, $standardCount, $wildcardCount) {
            $order = Order::lockForUpdate()->find($order->id);

            $hasIssuedCert = Cert::where('order_id', $order->id)
                ->whereNotNull('cert')
                ->where('cert', '!=', '')
                ->exists();

            if ($hasIssuedCert && ! $product->reissue) {
                throw new \Exception('Product does not support reissue');
            }

            $action = $hasIssuedCert ? 'reissue' : 'new';

            // 重签域名验证（参考传统 API 的 add_san/replace_san 规则）
            if ($action === 'reissue') {
                $lastIssuedCert = Cert::where('order_id', $order->id)
                    ->whereNotNull('cert')
                    ->where('cert', '!=', '')
                    ->orderBy('id', 'desc')
                    ->first();

                if ($lastIssuedCert) {
                    if (! $product->add_san) {
                        if ($standardCount > ($lastIssuedCert->standard_count ?? 0)) {
                            throw new \Exception('Standard domain count exceeds previous certificate');
                        }
                        if ($wildcardCount > ($lastIssuedCert->wildcard_count ?? 0)) {
                            throw new \Exception('Wildcard domain count exceeds previous certificate');
                        }
                    }

                    if (! $product->replace_san) {
                        $oldDomains = array_filter(explode(',', $lastIssuedCert->alternative_names ?? ''));
                        $missingDomains = array_diff(
                            array_map('strtolower', $oldDomains),
                            array_map('strtolower', $domains)
                        );
                        if (! empty($missingDomains)) {
                            throw new \Exception('Cannot remove domains from previous certificate: '.implode(', ', $missingDomains));
                        }
                    }
                }
            }

            $canReuse = in_array($currentLatestCert->status, ['unpaid', 'pending'])
                && empty($currentLatestCert->api_id)
                && empty($currentLatestCert->cert);

            if ($canReuse) {
                $cert = $currentLatestCert;
                $cert->acmeAuthorizations()->delete();

                if ($cert->action !== $action) {
                    $cert->update(['action' => $action]);
                }
            } else {
                $cert = Cert::create([
                    'order_id' => $order->id,
                    'last_cert_id' => $currentLatestCert->id,
                    'action' => $action,
                    'channel' => 'acme',
                    'email' => $user->email,
                    'common_name' => $domains[0] ?? '',
                    'status' => 'unpaid',
                ]);

                $order->latest_cert_id = $cert->id;
                $order->save();
            }

            // 写入域名信息（无论复用还是新建）
            $cert->update([
                'common_name' => $domains[0] ?? '',
                'alternative_names' => implode(',', $domains),
                'standard_count' => $standardCount,
                'wildcard_count' => $wildcardCount,
            ]);

            return $cert;
        });
    }

    /**
     * 扣费（幂等：仅当域名数量超过已购买数量时扣费）
     * 完成后 cert 状态变为 pending（已支付，待提交域名）
     */
    public function chargeCert(Cert $cert, Order $order): void
    {
        $standardCount = $cert->standard_count;
        $wildcardCount = $cert->wildcard_count;

        $needCharge = $standardCount > ($order->purchased_standard_count ?? 0)
            || $wildcardCount > ($order->purchased_wildcard_count ?? 0);

        DB::transaction(function () use ($cert, $order, $standardCount, $wildcardCount, $needCharge) {
            if ($needCharge) {
                $order = Order::lockForUpdate()->find($order->id);
                $product = $order->product;
                $user = $order->user;

                $cert->update(['status' => 'unpaid']);

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
            }

            $cert->update(['status' => 'pending']);
        });
    }

    /**
     * cert 复用/创建 + 扣费（兼容方法）
     */
    public function prepareAndCharge(Order $order, array $domains): Cert
    {
        $cert = $this->prepareCert($order, $domains);
        $this->chargeCert($cert, $order);

        return $cert;
    }

    /**
     * 提交新订单到上游（三步：chargeCert → prepareUpstreamOrder → submitUpstreamDomains）
     */
    public function submitNewOrder(Cert $cert, array $domains, Order $order): array
    {
        // 步骤1：扣费（unpaid → pending）
        $this->chargeCert($cert, $order);

        // 步骤2：创建上游 ACME order，获取 api_id（pending → processing）
        $prepareResult = $this->prepareUpstreamOrder($cert, $order);
        if (isset($prepareResult['error'])) {
            return $prepareResult;
        }

        // 步骤3：提交域名
        return $this->submitUpstreamDomains($cert, $domains, $order);
    }

    /**
     * 创建上游 ACME order，获取 api_id + EAB
     */
    public function prepareUpstreamOrder(Cert $cert, Order $order): array
    {
        $user = $order->user;
        $product = $order->product;
        $sourceApi = app(Api\Api::class)->getSourceApi($product->source ?? '');

        if (! $sourceApi->isConfigured()) {
            return ['error' => 'Upstream not configured'];
        }

        $prepareResult = $sourceApi->prepareOrder(
            $user->email ?? '',
            (string) $product->api_id,
            $cert->refer_id
        );

        if ($prepareResult['code'] !== 1) {
            return ['error' => $prepareResult['msg'] ?? 'Upstream order preparation failed'];
        }

        $cert->update([
            'api_id' => $prepareResult['data']['id'],
            'status' => 'processing',
        ]);

        return $prepareResult;
    }

    /**
     * 提交域名到上游，保存 authorizations
     */
    public function submitUpstreamDomains(Cert $cert, array $domains, Order $order): array
    {
        $sourceApi = app(Api\Api::class)->getSourceApi($order->product->source ?? '');

        if (! $sourceApi->isConfigured()) {
            return ['error' => 'Upstream not configured'];
        }

        $submitResult = $sourceApi->submitDomains((int) $cert->api_id, $domains);

        if ($submitResult['code'] !== 1) {
            return ['error' => $submitResult['msg'] ?? 'Upstream domain submission failed'];
        }

        return $this->saveUpstreamResult($cert, $domains, $order, $submitResult['data']);
    }

    /**
     * 提交重签到上游
     */
    public function submitReissue(Cert $cert, array $domains, Order $order, int $upstreamOrderId): array
    {
        $sourceApi = app(Api\Api::class)->getSourceApi($order->product->source ?? '');

        $apiResult = $sourceApi->reissueOrder(
            $upstreamOrderId,
            $domains,
            $cert->refer_id
        );

        if ($apiResult['code'] !== 1) {
            return ['error' => $apiResult['msg'] ?? 'Upstream reissue failed'];
        }

        return $this->saveUpstreamResult($cert, $domains, $order, $apiResult['data']);
    }

    /**
     * 保存上游返回结果（共享逻辑）
     */
    private function saveUpstreamResult(Cert $cert, array $domains, Order $order, array $apiData): array
    {
        $domainsString = implode(',', $domains);
        $sans = OrderUtil::getSansFromDomains($domainsString, $order->product->gift_root_domain ?? false);

        $cert->update([
            'common_name' => $domains[0] ?? '',
            'alternative_names' => $domainsString,
            'standard_count' => $sans['standard_count'],
            'wildcard_count' => $sans['wildcard_count'],
            'api_id' => $apiData['id'],
            'status' => 'processing',
            'cert_apply_status' => 2,
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
     * 通过上游吊销证书（供 CertificateController / ApiService 调用）
     *
     * 吊销策略（与取消策略一致）：
     * - 调用上游前先标记 revoking，上游失败不回退，保持 revoking 便于系统发现并重试
     * - 上游未配置时返回失败，由管理员排查配置问题，不可静默标记成功
     * - 上游返回明确成功后才标记 revoked
     */
    public function revokeCertificateUpstream(Cert $cert, string $reason = 'UNSPECIFIED'): array
    {
        $source = $cert->order?->product?->source ?? '';
        $sourceApi = app(Api\Api::class)->getSourceApi($source);

        // 上游未配置时返回失败，由管理员排查配置问题，不可静默标记成功
        if (! $sourceApi->isConfigured()) {
            return ['code' => 0, 'msg' => 'Upstream not configured'];
        }

        if (! $cert->serial_number) {
            return ['code' => 0, 'msg' => 'Certificate has no serial number'];
        }

        // 调用上游前先标记 revoking，上游失败不回退
        $cert->update(['status' => 'revoking']);

        $result = $sourceApi->revokeCertificate($cert->serial_number, $reason);

        if ($result['code'] !== 1) {
            $msg = $result['msg'] ?? 'Revocation failed';
            // "already revoked" 视为成功
            if (! str_contains(strtolower($msg), 'already revoked')) {
                return $result;
            }
        }

        $cert->update(['status' => 'revoked']);

        return ['code' => 1];
    }

    /**
     * 通过上游取消订单（供 BillingService 调用）
     *
     * 上游未配置时返回失败，不允许静默标记本地取消成功。
     * 取消/吊销的必要约束：上游返回明确成功后才能标记状态，配置缺失应由管理员处理。
     */
    public function cancelOrderUpstream(Cert $cert): array
    {
        $source = $cert->order?->product?->source ?? '';
        $sourceApi = app(Api\Api::class)->getSourceApi($source);

        // 上游未配置时返回失败，由管理员排查配置问题，不可静默标记成功
        if (! $sourceApi->isConfigured()) {
            return ['code' => 0, 'msg' => 'Upstream not configured'];
        }

        return $sourceApi->cancelOrder((int) $cert->api_id);
    }

    /**
     * 通过上游获取证书（供 ApiService 调用）
     */
    public function getCertificateFromUpstream(Cert $cert): ?array
    {
        $source = $cert->order?->product?->source ?? '';
        $sourceApi = app(Api\Api::class)->getSourceApi($source);

        if (! $sourceApi->isConfigured() || ! $cert->api_id) {
            return null;
        }

        $result = $sourceApi->getCertificate((int) $cert->api_id);

        return $result['code'] === 1 ? $result['data'] : null;
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
