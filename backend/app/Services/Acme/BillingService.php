<?php

declare(strict_types=1);

namespace App\Services\Acme;

use App\Models\Acme\Account;
use App\Models\Acme\AcmeCert;
use App\Models\Acme\AcmeOrder;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Order\Utils\OrderUtil;
use App\Services\Order\Utils\ValidatorUtil;
use Illuminate\Support\Facades\Cache;
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
    public function cancelOrder(AcmeOrder $order): void
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
     * - 未提交上游（无 api_id）：直接删除清理 + 退费
     * - 已提交上游（有 api_id）：要求上游明确成功，仅标记状态 + 退费，不删除订单和相关信息
     */
    public function executeCancel(AcmeOrder $order): array
    {
        $cert = $order->latestCert;

        // 先设为 cancelling，与传统 API 取消流程对齐
        $cert->update(['status' => 'cancelling']);

        $submitted = (bool) $cert->api_id;

        // 已提交上游的订单，必须上游明确返回成功
        if ($submitted) {
            $result = app(OrderService::class)->cancelOrderUpstream($cert);
            if ($result['code'] !== 1) {
                // 上游取消失败，保持 cancelling 状态，便于系统发现并重试
                return ['code' => 0, 'msg' => $result['msg'] ?? '上游取消失败'];
            }
        }

        // 本地 DB 更新包在事务内保证原子性
        DB::transaction(function () use ($order, $cert, $submitted) {
            // 创建取消交易退费
            $transaction = OrderUtil::getCancelTransaction($order->toArray(), Transaction::TYPE_ACME_CANCEL);
            Transaction::create($transaction);

            // 标记 cert 为 cancelled
            $cert->update(['status' => 'cancelled']);

            // 未提交上游的 pending 订单：清理关联数据
            if (! $submitted) {
                $cert->acmeAuthorizations()->delete();
                Account::where('order_id', $order->id)->delete();
            }

            // 保存取消时间
            $order->update(['cancelled_at' => now()]);
        });

        return ['code' => 1];
    }

    /**
     * 创建 ACME 订阅
     *
     * @param  array|null  $domains  域名列表（Web 端传入，创建 unpaid 订单；不传则 pending，用于 Deploy/自动续费）
     * @param  string|null  $validationMethod  验证方式（delegation/txt/file_proxy/file）
     */
    public function createSubscription(User $user, int $productId, int $period, ?array $domains = null, ?string $validationMethod = null): array
    {
        // 防重复创建（10 秒内相同参数）
        $cacheKey = 'acme_create_'.md5(json_encode([$user->id, $productId, $period, $domains]));
        if (Cache::has($cacheKey)) {
            return ['code' => 0, 'msg' => '请勿重复提交，请稍后再试'];
        }
        Cache::put($cacheKey, time(), 10);

        $product = Product::where('id', $productId)
            ->where('product_type', Product::TYPE_ACME)
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

        // 有域名时做 SAN 验证
        if ($domains) {
            $domainsString = implode(',', $domains);
            $sanErrors = ValidatorUtil::validateSansMaxCount($product->toArray(), $domainsString);
            if (! empty($sanErrors)) {
                return ['code' => 0, 'msg' => implode('; ', array_values($sanErrors))];
            }
        }

        try {
            $result = DB::transaction(function () use ($user, $product, $period, $domains, $validationMethod) {
                $order = AcmeOrder::create([
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'brand' => $product->brand,
                    'period' => $period,
                    'amount' => 0,
                    'period_from' => now(),
                    'period_till' => now()->addMonths($period),
                    'auto_renew' => true,
                ]);

                // 有域名 → unpaid（Web 端，用户需点击支付）；无域名 → pending（Deploy/自动续费）
                $certData = [
                    'order_id' => $order->id,
                    'action' => 'new',
                    'channel' => 'api',
                    'common_name' => '',
                    'email' => $user->email,
                    'amount' => '0.00',
                    'status' => $domains ? 'unpaid' : 'pending',
                ];

                if ($domains) {
                    $domainsString = implode(',', $domains);
                    $sans = OrderUtil::getSansFromDomains($domainsString, $product->gift_root_domain ?? false);
                    $certData['common_name'] = $domains[0] ?? '';
                    $certData['alternative_names'] = $domainsString;
                    $certData['standard_count'] = $sans['standard_count'];
                    $certData['wildcard_count'] = $sans['wildcard_count'];
                    $certData['validation_method'] = $validationMethod ?? 'txt';
                    $certData['validation'] = array_map(fn ($d) => [
                        'domain' => $d,
                        'method' => in_array($validationMethod, ['delegation', 'txt']) ? 'txt' : ($validationMethod ?? 'txt'),
                        'verified' => 0,
                    ], $domains);
                }

                $cert = AcmeCert::create($certData);

                $order->latest_cert_id = $cert->id;
                $order->amount = '0.00';

                if ($domains) {
                    $order->purchased_standard_count = $cert->standard_count ?? 0;
                    $order->purchased_wildcard_count = $cert->wildcard_count ?? 0;
                } else {
                    $order->purchased_standard_count = 0;
                    $order->purchased_wildcard_count = 0;
                }

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
    public function tryAutoRenew(User $user, AcmeOrder $lastOrder): array
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
                $locked = AcmeOrder::where('id', $lastOrder->id)->lockForUpdate()->first();

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

                // 创建 AcmeOrder
                $order = AcmeOrder::create([
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'brand' => $product->brand,
                    'period' => $period,
                    'amount' => 0,
                    'period_from' => now(),
                    'period_till' => now()->addMonths($period),
                    'auto_renew' => true,
                ]);

                // 创建 AcmeCert（域名数量和金额在扣费时写入）
                $cert = AcmeCert::create([
                    'order_id' => $order->id,
                    'action' => 'new',
                    'channel' => 'api',
                    'common_name' => '',
                    'email' => $user->email,
                    'amount' => '0.00',
                    'status' => 'pending',
                ]);

                $order->latest_cert_id = $cert->id;
                $order->amount = '0.00';
                $order->purchased_standard_count = 0;
                $order->purchased_wildcard_count = 0;
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
