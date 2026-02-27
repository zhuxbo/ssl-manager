<?php

declare(strict_types=1);

namespace App\Services\Acme;

use App\Models\Acme\Account;
use App\Models\Cert;
use App\Models\Order;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Order\Utils\OrderUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingService
{
    /**
     * 检查 ACME 账户是否可以签发证书（通过 account->order 精确匹配）
     */
    public function canIssueCertificate(Account $account): array
    {
        $order = $account->order;

        // 精确匹配：检查关联订单是否有效
        if ($order && $order->period_till > now() && $order->cancelled_at === null) {
            return [
                'allowed' => true,
                'order' => $order,
                'message' => 'Valid order found',
            ];
        }

        // 关联订单已过期，检查续费开关（订单级 → 用户级回落）
        if ($order) {
            $user = $account->user;
            $autoRenewEnabled = $order->auto_renew ?? ($user->auto_settings['auto_renew'] ?? false);

            if (! $autoRenewEnabled) {
                return [
                    'allowed' => false,
                    'error' => 'orderNotReady',
                    'detail' => 'Auto-renew is disabled for this order',
                ];
            }

            $renewResult = $this->tryAutoRenew($user, $order);

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
            'detail' => 'No valid order associated with this ACME account',
        ];
    }

    /**
     * 取消 ACME 订单（清理 ACME 特有数据，由 cancelPending 调用）
     */
    public function cancelOrder(Order $order): void
    {
        $cert = $order->latestCert;
        if ($cert) {
            $cert->acmeAuthorizations()->delete();
        }
        Account::where('order_id', $order->id)->delete();
    }

    /**
     * 执行 ACME 订单取消（通知上级 + 退费 + 清理）
     *
     * 由 Action::cancel()（task 执行）和 ApiService::cancelOrder()（协议端点）调用
     */
    /**
     * 执行 ACME 订单取消（通知上级 + 退费 + 清理）
     *
     * 由 Action::cancel()（task 执行）和 ApiService::cancelOrder()（协议端点）调用
     * latestCert 必须存在，这是系统约束——不存在时应报错而非静默处理
     */
    public function executeCancel(Order $order): void
    {
        $cert = $order->latestCert;

        // 先设为 cancelling，与传统 API 取消流程对齐
        $cert->update(['status' => 'cancelling']);

        // 通知上级取消（best-effort，事务外执行避免 CA 成功但本地回滚的不一致）
        $apiClient = app(ApiClient::class);
        if ($cert->api_id && $apiClient->isConfigured()) {
            try {
                $apiClient->cancelOrder((int) $cert->api_id);
            } catch (\Exception $e) {
                // best-effort，不阻断本地清理
            }
        }

        // 本地 DB 更新包在事务内保证原子性
        DB::transaction(function () use ($order, $cert) {
            // 创建取消交易退费
            $transaction = OrderUtil::getCancelTransaction($order->toArray());
            Transaction::create($transaction);

            // 标记 cert 为 cancelled
            $cert->update(['status' => 'cancelled']);

            // 清理 ACME authorizations
            $cert->acmeAuthorizations()->delete();

            // 清理 ACME account 关联
            Account::where('order_id', $order->id)->delete();

            // 保存取消时间
            $order->update(['cancelled_at' => now()]);
        });
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

                // 检查该 Account 是否已迁移到新 Order（说明已续费过）
                $account = Account::where('order_id', $lastOrder->id)->lockForUpdate()->first();
                if (! $account) {
                    // Account 已不关联此 Order，说明已续费迁移
                    $migratedOrder = Account::where('order_id', '!=', $lastOrder->id)
                        ->whereHas('order', fn ($q) => $q->where('user_id', $user->id)
                            ->where('period_till', '>', $locked->period_till)
                            ->whereNull('cancelled_at'))
                        ->first()?->order;
                    if ($migratedOrder) {
                        return $migratedOrder;
                    }
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

            return ['code' => 1, 'data' => ['order' => $newOrder]];
        } catch (\Exception $e) {
            return ['code' => 0, 'msg' => $e->getMessage()];
        }
    }
}
