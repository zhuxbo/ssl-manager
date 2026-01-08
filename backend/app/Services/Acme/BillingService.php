<?php

declare(strict_types=1);

namespace App\Services\Acme;

use App\Models\Acme\AcmeAccount;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BillingService
{
    /**
     * 检查用户是否可以签发证书
     */
    public function canIssueCertificate(AcmeAccount $account): array
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

            if ($renewResult['success']) {
                return [
                    'allowed' => true,
                    'order' => $renewResult['order'],
                    'message' => 'Auto-renewed',
                ];
            }

            return [
                'allowed' => false,
                'error' => 'orderNotReady',
                'detail' => $renewResult['message'],
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
            ->whereHas('product', fn($q) => $q->where('product_type', 'acme'))
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
            ->whereHas('product', fn($q) => $q->where('product_type', 'acme'))
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * 尝试自动续费
     */
    public function tryAutoRenew(User $user, Order $lastOrder): array
    {
        $product = $lastOrder->product;

        if (!$product) {
            return ['success' => false, 'message' => 'Product not found'];
        }

        // 获取产品价格
        $price = $this->getProductPrice($product, $user, $lastOrder->period);

        if ($price === null) {
            return ['success' => false, 'message' => 'Price not available'];
        }

        // 检查用户余额
        $balance = $user->funds->first()?->balance ?? 0;

        if ($balance < $price) {
            return ['success' => false, 'message' => 'Insufficient balance'];
        }

        // 执行扣费和创建订单
        try {
            $newOrder = DB::transaction(function () use ($user, $product, $lastOrder, $price) {
                // 扣除余额
                $this->deductBalance($user, $price, 'ACME 自动续费');

                // 创建新订单
                return $this->createOrder($user, $product, $lastOrder->period, $price);
            });

            return ['success' => true, 'order' => $newOrder];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 获取产品价格
     */
    private function getProductPrice(Product $product, User $user, int $period): ?float
    {
        $userLevel = $user->level ?? null;
        $levelId = $userLevel?->id ?? 0;

        $priceRecord = $product->prices()
            ->where('user_level_id', $levelId)
            ->where('period', $period)
            ->first();

        return $priceRecord?->price;
    }

    /**
     * 扣除用户余额
     */
    private function deductBalance(User $user, float $amount, string $remark): void
    {
        $fund = $user->funds->first();

        if (!$fund) {
            throw new \Exception('User has no fund account');
        }

        if ($fund->balance < $amount) {
            throw new \Exception('Insufficient balance');
        }

        $fund->decrement('balance', $amount);

        // 记录交易
        $user->transactions()->create([
            'fund_id' => $fund->id,
            'amount' => -$amount,
            'balance' => $fund->balance,
            'type' => 'consume',
            'remark' => $remark,
        ]);
    }

    /**
     * 创建新订单
     */
    private function createOrder(User $user, Product $product, int $period, float $amount): Order
    {
        $snowflake = app(\Godruoyi\Snowflake\Snowflake::class);

        return Order::create([
            'id' => $snowflake->id(),
            'user_id' => $user->id,
            'product_id' => $product->id,
            'brand' => $product->brand,
            'period' => $period,
            'amount' => $amount,
            'period_from' => now(),
            'period_till' => now()->addMonths($period),
            'auto_renew' => true,
        ]);
    }
}
