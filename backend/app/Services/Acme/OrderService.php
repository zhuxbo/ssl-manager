<?php

declare(strict_types=1);

namespace App\Services\Acme;

use App\Models\Acme\AcmeAccount;
use App\Models\Acme\AcmeAuthorization;
use App\Models\Acme\AcmeOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        private BillingService $billingService,
        private UpstreamClient $upstreamClient
    ) {}

    /**
     * 创建 ACME 订单
     */
    public function create(AcmeAccount $account, array $identifiers): array
    {
        // 检查计费
        $billingCheck = $this->billingService->canIssueCertificate($account);

        if (! $billingCheck['allowed']) {
            return [
                'error' => $billingCheck['error'],
                'detail' => $billingCheck['detail'],
            ];
        }

        $billingOrder = $billingCheck['order'];

        // 调用上游创建订单（如果配置了上游）
        $upstreamOrder = null;
        if (config('acme.api.base_url')) {
            $domains = array_map(fn ($i) => $i['value'], $identifiers);
            $result = $this->upstreamClient->createOrder(
                $account->id,
                $domains,
                $billingOrder->product->code ?? ''
            );

            if ($result['code'] !== 1) {
                return [
                    'error' => 'serverInternal',
                    'detail' => $result['msg'] ?? 'Upstream order creation failed',
                ];
            }

            $upstreamOrder = $result['data'];
        }

        // 创建本地订单
        return DB::transaction(function () use ($account, $identifiers, $billingOrder) {
            $order = AcmeOrder::create([
                'acme_account_id' => $account->id,
                'order_id' => $billingOrder->id,
                'identifiers' => $identifiers,
                'expires' => now()->addDays(7),
                'status' => 'pending',
                'finalize_token' => Str::random(32),
            ]);

            // 创建授权
            foreach ($identifiers as $identifier) {
                $isWildcard = str_starts_with($identifier['value'], '*.');

                AcmeAuthorization::create([
                    'acme_order_id' => $order->id,
                    'token' => Str::random(32),
                    'identifier_type' => $identifier['type'],
                    'identifier_value' => $identifier['value'],
                    'wildcard' => $isWildcard,
                    'status' => 'pending',
                    'expires' => now()->addDays(7),
                    'challenge_type' => $isWildcard ? 'dns-01' : 'http-01',
                    'challenge_token' => Str::random(43),
                    'challenge_status' => 'pending',
                ]);
            }

            return ['order' => $order->fresh('authorizations')];
        });
    }

    /**
     * 获取订单详情
     */
    public function get(string $token): ?AcmeOrder
    {
        return AcmeOrder::where('finalize_token', $token)
            ->orWhere('certificate_token', $token)
            ->with('authorizations')
            ->first();
    }

    /**
     * 通过 ID 获取订单
     */
    public function getById(int $id): ?AcmeOrder
    {
        return AcmeOrder::with('authorizations')->find($id);
    }

    /**
     * 获取授权
     */
    public function getAuthorization(string $token): ?AcmeAuthorization
    {
        return AcmeAuthorization::where('token', $token)->first();
    }

    /**
     * 响应验证挑战
     */
    public function respondToChallenge(AcmeAuthorization $authorization): array
    {
        // TODO: 实际验证逻辑（检查 HTTP 文件或 DNS 记录）
        // 这里简化为直接标记为有效

        $authorization->update([
            'challenge_status' => 'valid',
            'challenge_validated' => now(),
            'status' => 'valid',
        ]);

        // 检查订单的所有授权是否都已通过
        $order = $authorization->order;
        $allValid = $order->authorizations()
            ->where('status', '!=', 'valid')
            ->doesntExist();

        if ($allValid) {
            $order->update(['status' => 'ready']);
        }

        return ['status' => 'valid'];
    }

    /**
     * 完成订单（提交 CSR）
     */
    public function finalize(AcmeOrder $order, string $csr): array
    {
        if ($order->status !== 'ready') {
            return [
                'error' => 'orderNotReady',
                'detail' => "Order is not ready for finalization, current status: $order->status",
            ];
        }

        $order->update(['status' => 'processing', 'csr' => $csr]);

        // 调用上游完成订单
        if (config('acme.api.base_url')) {
            $result = $this->upstreamClient->finalizeOrder($order->id, $csr);

            if ($result['code'] !== 1) {
                $order->update(['status' => 'invalid']);

                return [
                    'error' => 'serverInternal',
                    'detail' => $result['msg'] ?? 'Upstream finalization failed',
                ];
            }

            // 获取证书
            $certResult = $this->upstreamClient->getCertificate($order->id);

            if ($certResult['code'] === 1) {
                $certData = $certResult['data'];
                $order->update([
                    'status' => 'valid',
                    'certificate_token' => Str::random(32),
                    'certificate' => $certData['certificate'] ?? '',
                    'chain' => $certData['chain'] ?? '',
                ]);
            }
        } else {
            // 必须配置上游 Gateway
            $order->update(['status' => 'invalid']);

            return [
                'error' => 'serverInternal',
                'detail' => 'Upstream gateway not configured (ACME_GATEWAY_URL)',
            ];
        }

        return ['order' => $order->fresh()];
    }

    /**
     * 生成订单 URL
     */
    public function getOrderUrl(AcmeOrder $order): string
    {
        $baseUrl = rtrim(config('app.url'), '/');

        return "$baseUrl/acme/order/$order->finalize_token";
    }

    /**
     * 生成 Finalize URL
     */
    public function getFinalizeUrl(AcmeOrder $order): string
    {
        $baseUrl = rtrim(config('app.url'), '/');

        return "$baseUrl/acme/order/$order->finalize_token/finalize";
    }

    /**
     * 生成证书 URL
     */
    public function getCertificateUrl(AcmeOrder $order): string
    {
        $baseUrl = rtrim(config('app.url'), '/');

        return "$baseUrl/acme/cert/$order->certificate_token";
    }

    /**
     * 生成授权 URL
     */
    public function getAuthorizationUrl(AcmeAuthorization $authorization): string
    {
        $baseUrl = rtrim(config('app.url'), '/');

        return "$baseUrl/acme/authz/$authorization->token";
    }

    /**
     * 生成挑战 URL
     */
    public function getChallengeUrl(AcmeAuthorization $authorization): string
    {
        $baseUrl = rtrim(config('app.url'), '/');

        return "$baseUrl/acme/chall/$authorization->token";
    }

    /**
     * 格式化订单响应
     */
    public function formatOrderResponse(AcmeOrder $order): array
    {
        $response = [
            'status' => $order->status,
            'expires' => $order->expires?->toIso8601String(),
            'identifiers' => $order->identifiers,
            'authorizations' => $order->authorizations->map(
                fn ($a) => $this->getAuthorizationUrl($a)
            )->toArray(),
            'finalize' => $this->getFinalizeUrl($order),
        ];

        if ($order->status === 'valid' && $order->certificate_token) {
            $response['certificate'] = $this->getCertificateUrl($order);
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
            'expires' => $authorization->expires?->toIso8601String(),
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
            $response['validated'] = $authorization->challenge_validated->toIso8601String();
        }

        return $response;
    }
}
