<?php

declare(strict_types=1);

namespace App\Services\Acme;

use App\Jobs\TaskJob;
use App\Models\Acme;
use App\Models\Product;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Acme\Api\Api;
use App\Services\Order\Utils\OrderUtil;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Action
{
    use ApiResponse;

    protected int $userId;

    public function __construct(int $userId = 0)
    {
        $this->userId = $userId;
    }

    /**
     * 创建 ACME 订单（unpaid 状态）
     */
    public function new(User $user, int $productId, int $period, int $standardCount, int $wildcardCount, ?string $remark = null): Acme
    {
        $product = Product::where('id', $productId)
            ->where('product_type', Product::TYPE_ACME)
            ->first();

        if (! $product) {
            $this->error('产品不存在或不支持 ACME');
        }

        if (! in_array($period, $product->periods)) {
            $this->error('无效的购买时长');
        }

        // 计算订单金额
        $amount = OrderUtil::getLatestCertAmount(
            ['user_id' => $user->id, 'product_id' => $productId, 'period' => $period, 'purchased_standard_count' => 0, 'purchased_wildcard_count' => 0],
            ['standard_count' => $standardCount, 'wildcard_count' => $wildcardCount, 'action' => 'new'],
            $product->toArray()
        );

        return Acme::create([
            'user_id' => $user->id,
            'product_id' => $productId,
            'brand' => $product->brand,
            'period' => $period,
            'purchased_standard_count' => $standardCount,
            'purchased_wildcard_count' => $wildcardCount,
            'refer_id' => bin2hex(random_bytes(16)),
            'amount' => $amount,
            'status' => Acme::STATUS_UNPAID,
            'remark' => $remark,
        ]);
    }

    /**
     * 支付订单 — 扣费，状态 → pending
     */
    public function pay(Acme $acme): Acme
    {
        if ($acme->status !== Acme::STATUS_UNPAID) {
            $this->error('订单不是未支付状态');
        }

        $user = $acme->user;

        // 构造交易数据（金额取负数表示扣费）
        $transactionAmount = '-'.$acme->amount;

        // 验证余额是否足够
        $balanceAfter = bcadd((string) $user->balance, $transactionAmount, 2);
        if (bccomp($balanceAfter, (string) $user->credit_limit, 2) === -1) {
            $this->error('余额不足');
        }

        DB::transaction(function () use ($acme, $user, $transactionAmount) {
            Transaction::create([
                'user_id' => $user->id,
                'type' => Transaction::TYPE_ACME_ORDER,
                'transaction_id' => $acme->id,
                'amount' => $transactionAmount,
                'standard_count' => $acme->purchased_standard_count,
                'wildcard_count' => $acme->purchased_wildcard_count,
            ]);

            $acme->update(['status' => Acme::STATUS_PENDING]);
        });

        return $acme->refresh();
    }

    /**
     * 提交订单到 Gateway — 成功后状态 → active
     */
    public function commit(Acme $acme): Acme
    {
        if ($acme->status !== Acme::STATUS_PENDING) {
            $this->error('订单状态不是待提交');
        }

        $product = $acme->product;

        $data = [
            'source' => $product->source,
            'product_api_id' => $product->api_id,
            'product_type' => $product->product_type,
            'period' => $acme->period,
            'purchased_standard_count' => $acme->purchased_standard_count,
            'purchased_wildcard_count' => $acme->purchased_wildcard_count,
            'refer_id' => $acme->refer_id,
        ];

        try {
            $result = (new Api)->new($data);
        } catch (\Throwable $e) {
            // 提交失败，保持 pending 状态，用户可重试或通过取消流程退费
            $this->error($e->getMessage());
        }

        $acme->update([
            'api_id' => $result['data']['api_id'] ?? null,
            'vendor_id' => $result['data']['vendor_id'] ?? null,
            'eab_kid' => $result['data']['eab_kid'] ?? null,
            'eab_hmac' => $result['data']['eab_hmac'] ?? null,
            'period_from' => now(),
            'period_till' => now()->addMonths($acme->period),
            'status' => Acme::STATUS_ACTIVE,
        ]);

        return $acme->refresh();
    }

    /**
     * 提交取消 — 标记 cancelling + 创建延时任务
     */
    public function commitCancel(Acme $acme): Acme
    {
        if (! in_array($acme->status, [Acme::STATUS_ACTIVE, Acme::STATUS_PENDING])) {
            $this->error('当前状态不允许取消');
        }

        // 未提交上游的 pending 订单，直接退费取消
        if ($acme->status === Acme::STATUS_PENDING && ! $acme->api_id) {
            $this->refund($acme);
            $acme->update([
                'status' => Acme::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            return $acme->refresh();
        }

        $acme->update([
            'status' => Acme::STATUS_CANCELLING,
            'cancelled_at' => now(),
        ]);

        // 检查是否已存在相同的执行中任务，避免重复创建
        $existingTask = Task::where('order_id', $acme->id)
            ->where('action', 'cancel_acme')
            ->where('status', 'executing')
            ->first();

        if (! $existingTask) {
            $taskData = [
                'order_id' => $acme->id,
                'action' => 'cancel_acme',
                'started_at' => now()->addSeconds(120),
                'status' => 'executing',
                'source' => getControllerCategory(),
            ];
            $this->userId && $taskData['user_id'] = $this->userId;

            $task = Task::create($taskData);

            TaskJob::dispatch(['id' => $task->id])
                ->delay(now()->addSeconds(123))
                ->onQueue(config('queue.names.tasks'));
        }

        return $acme->refresh();
    }

    /**
     * 执行取消 — 延时任务调用，调 Api->cancel()，退费处理
     */
    public function cancel(Acme $acme): array
    {
        if ($acme->status !== Acme::STATUS_CANCELLING) {
            return ['code' => 0, 'msg' => '订单状态不是取消中'];
        }

        // 调用上游取消
        if ($acme->api_id) {
            try {
                $result = (new Api)->cancel($acme->id);
            } catch (\Throwable $e) {
                // 上游取消失败，保持 cancelling 状态
                return ['code' => 0, 'msg' => $e->getMessage()];
            }

            // 上游返回吊销状态
            $status = $result['data']['status'] ?? '';
            if ($status === 'revoked') {
                $this->refund($acme);
                $acme->update(['status' => Acme::STATUS_REVOKED]);

                return ['code' => 1];
            }
        }

        // 退费并标记已取消
        $this->refund($acme);
        $acme->update(['status' => Acme::STATUS_CANCELLED]);

        return ['code' => 1];
    }

    /**
     * 同步订单状态 — 从 Gateway 拉取最新状态
     *
     * @param  bool  $force  true 时静默返回（供 get 内部调用），false 时返回 success 响应（供 Admin 调用）
     */
    public function sync(int $acmeId, bool $force = false): void
    {
        // 10秒内不重复向上请求
        $cacheKey = "acme_sync_$acmeId";
        if (Cache::get($cacheKey)) {
            if ($force) {
                return;
            }
            $this->success();
        }

        $acme = Acme::find($acmeId);

        if (! $acme || ! $acme->api_id) {
            if ($force) {
                return;
            }
            $this->error($acme ? '订单尚未提交到上游' : '订单不存在');
        }

        $result = (new Api)->get($acme->id);

        $data = $result['data'] ?? [];
        $updateData = [];
        $syncableStatuses = [Acme::STATUS_ACTIVE, Acme::STATUS_REVOKED, Acme::STATUS_EXPIRED, Acme::STATUS_CANCELLED];
        if (isset($data['status']) && in_array($data['status'], $syncableStatuses)) {
            $updateData['status'] = $data['status'];
        }
        if (isset($data['vendor_id'])) {
            $updateData['vendor_id'] = $data['vendor_id'];
        }
        if (! empty($updateData)) {
            $acme->update($updateData);
        }

        Cache::set($cacheKey, time(), 10);

        if (! $force) {
            $this->success();
        }
    }

    /**
     * 退费处理（内部方法）
     */
    private function refund(Acme $acme): void
    {
        $transaction = OrderUtil::getCancelTransaction(
            $acme->toArray(),
            Transaction::TYPE_ACME_CANCEL
        );
        Transaction::create($transaction);
    }
}
