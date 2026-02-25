<?php

declare(strict_types=1);

namespace App\Services\Acme;

use App\Models\Acme\Account;
use App\Models\Cert;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BillingService
{
    public function __construct(
        private ApiClient $apiClient
    ) {}

    /**
     * 检查用户是否可以签发证书
     */
    public function canIssueCertificate(Account $account): array
    {
        $user = $account->user;

        // 查找用户的有效 ACME 订单
        $order = $this->findValidOrder($user);

        if ($order) {
            return [
                'allowed' => true,
                'order' => $order,
                'message' => 'Valid order found',
            ];
        }

        // 检查是否可以自动续费
        $lastOrder = $this->findLastOrder($user);

        if ($lastOrder && $lastOrder->auto_renew) {
            $renewResult = $this->tryAutoRenew($user, $lastOrder);

            if ($renewResult['code'] === 1) {
                return [
                    'allowed' => true,
                    'order' => $renewResult['data']['order'],
                    'message' => 'Auto-renewed',
                ];
            }

            return [
                'allowed' => false,
                'error' => 'orderNotReady',
                'detail' => $renewResult['msg'],
            ];
        }

        return [
            'allowed' => false,
            'error' => 'orderNotReady',
            'detail' => 'No valid order and auto-renew not enabled',
        ];
    }

    /**
     * 查找用户的有效 ACME 订单
     */
    public function findValidOrder(User $user): ?Order
    {
        return Order::where('user_id', $user->id)
            ->whereHas('product', fn ($q) => $q->where('support_acme', 1))
            ->where('period_till', '>', now())
            ->whereNull('cancelled_at')
            ->orderBy('period_till', 'desc')
            ->first();
    }

    /**
     * 查找用户的最后一个 ACME 订单
     */
    public function findLastOrder(User $user): ?Order
    {
        return Order::where('user_id', $user->id)
            ->whereHas('product', fn ($q) => $q->where('support_acme', 1))
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Web 端创建 ACME 订阅
     */
    public function createSubscription(User $user, int $productId, int $period): array
    {
        $product = Product::where('id', $productId)
            ->where('support_acme', 1)
            ->first();

        if (! $product) {
            return ['code' => 0, 'msg' => '产品不存在或不支持 ACME'];
        }

        if (! in_array($period, $product->periods)) {
            return ['code' => 0, 'msg' => '无效的购买时长'];
        }

        if (empty($user->email)) {
            return ['code' => 0, 'msg' => '请先设置用户邮箱，ACME 账户注册需要邮箱地址'];
        }

        // 幂等检查：查找用户已有的同产品有效 ACME 订单
        $existingOrder = Order::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->where('period_till', '>', now())
            ->whereNull('cancelled_at')
            ->whereNotNull('eab_kid')
            ->lockForUpdate()
            ->first();

        if ($existingOrder) {
            $serverUrl = rtrim(get_system_setting('site', 'url', config('app.url')), '/').'/acme/directory';

            return [
                'code' => 1,
                'data' => [
                    'order' => $existingOrder,
                    'eab_kid' => $existingOrder->eab_kid,
                    'eab_hmac' => $existingOrder->eab_hmac,
                    'server_url' => $serverUrl,
                ],
            ];
        }

        try {
            $result = DB::transaction(function () use ($user, $product, $period) {
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

                $order->eab_kid = Str::uuid()->toString();
                $order->eab_hmac = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
                $order->save();

                return $order;
            });

            // 通知上游（best-effort）
            if ($this->apiClient->isConfigured()) {
                try {
                    $upstreamResult = $this->apiClient->createAccount($user->email, (string) $product->api_id);
                    if ($upstreamResult['code'] === 1 && isset($upstreamResult['data']['id'])) {
                        $result->update(['acme_account_id' => $upstreamResult['data']['id']]);
                    } else {
                        Log::warning('createSubscription upstream failed', [
                            'order_id' => $result->id,
                            'msg' => $upstreamResult['msg'] ?? 'Unknown error',
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('createSubscription upstream exception', [
                        'order_id' => $result->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $serverUrl = rtrim(get_system_setting('site', 'url', config('app.url')), '/').'/acme/directory';

            return [
                'code' => 1,
                'data' => [
                    'order' => $result,
                    'eab_kid' => $result->eab_kid,
                    'eab_hmac' => $result->eab_hmac,
                    'server_url' => $serverUrl,
                ],
            ];
        } catch (\Exception $e) {
            return ['code' => 0, 'msg' => $e->getMessage()];
        }
    }

    /**
     * 尝试自动续费 — 标准 Transaction 流程
     */
    public function tryAutoRenew(User $user, Order $lastOrder): array
    {
        if (empty($user->email)) {
            return ['code' => 0, 'msg' => 'User has no email for ACME account'];
        }

        $product = $lastOrder->product;

        if (! $product) {
            return ['code' => 0, 'msg' => 'Product not found'];
        }

        $period = $lastOrder->period;

        try {
            $newOrder = DB::transaction(function () use ($user, $product, $period, $lastOrder) {
                // M7: 锁定 lastOrder 防止并发续费
                $locked = Order::where('id', $lastOrder->id)->lockForUpdate()->first();

                // 检查是否已有新续费 Order（period_till > lastOrder.period_till）
                $existingRenewal = Order::where('user_id', $user->id)
                    ->whereHas('product', fn ($q) => $q->where('support_acme', 1))
                    ->where('period_till', '>', $locked->period_till)
                    ->whereNull('cancelled_at')
                    ->first();

                if ($existingRenewal) {
                    return $existingRenewal;
                }

                // 创建 Order
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

                // 创建 Cert
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

                // 生成 EAB
                $order->eab_kid = Str::uuid()->toString();
                $order->eab_hmac = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
                $order->save();

                // 迁移 Account 关联到新 Order
                Account::where('order_id', $lastOrder->id)
                    ->update(['order_id' => $order->id]);

                return $order;
            });

            // 通知上游创建新 Certum 订单（best-effort）
            if ($this->apiClient->isConfigured()) {
                try {
                    $upstreamResult = $this->apiClient->createAccount($user->email, (string) $product->api_id);
                    if ($upstreamResult['code'] === 1 && isset($upstreamResult['data']['id'])) {
                        $newOrder->update(['acme_account_id' => $upstreamResult['data']['id']]);
                    } else {
                        Log::warning('Auto-renew upstream createAccount failed', [
                            'order_id' => $newOrder->id,
                            'msg' => $upstreamResult['msg'] ?? 'Unknown error',
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Auto-renew upstream createAccount exception', [
                        'order_id' => $newOrder->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return ['code' => 1, 'data' => ['order' => $newOrder]];
        } catch (\Exception $e) {
            return ['code' => 0, 'msg' => $e->getMessage()];
        }
    }
}
