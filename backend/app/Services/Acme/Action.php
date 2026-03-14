<?php

declare(strict_types=1);

namespace App\Services\Acme;

use App\Exceptions\ApiResponseException;
use App\Jobs\TaskJob;
use App\Models\Acme;
use App\Models\Product;
use App\Models\Task;
use App\Models\Transaction;
use App\Services\Acme\Api\Api;
use App\Services\Order\Utils\OrderUtil;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Action
{
    use ApiResponse;

    /**
     * 创建 ACME 订单（unpaid 状态）
     */
    public function new(array $params): void
    {
        $acme = $this->createOrder($params);
        $this->success(['order_id' => $acme->id]);
    }

    /**
     * 支付订单 — 扣费，状态 → pending
     */
    public function pay(int $acmeId): void
    {
        $acme = Acme::findOrFail($acmeId);
        $this->payOrder($acme);
        $this->success();
    }

    /**
     * 提交订单到 Gateway — 成功后状态 → active
     */
    public function commit(int $acmeId): void
    {
        $acme = Acme::findOrFail($acmeId);
        $acme = $this->commitOrder($acme);

        $acme->makeVisible('eab_hmac');
        $this->success([
            'order_id' => $acme->id,
            'eab_kid' => $acme->eab_kid,
            'eab_hmac' => $acme->eab_hmac,
        ]);
    }

    /**
     * Deploy 一步到位：创建 + 支付 + 提交
     */
    public function deployNew(array $params): void
    {
        $acme = $this->createOrder($params);
        $acme = $this->payOrder($acme);
        $acme = $this->commitOrder($acme);

        $acme->makeVisible('eab_hmac');
        $this->success([
            'order_id' => $acme->id,
            'eab_kid' => $acme->eab_kid,
            'eab_hmac' => $acme->eab_hmac,
            'status' => $acme->status,
        ]);
    }

    /**
     * 提交取消 — 标记 cancelling + 创建延时任务
     */
    public function commitCancel(int $acmeId): void
    {
        $acme = Acme::findOrFail($acmeId);

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

            $this->success();
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
            $task = Task::create([
                'order_id' => $acme->id,
                'user_id' => $acme->user_id,
                'action' => 'cancel_acme',
                'started_at' => now()->addSeconds(120),
                'status' => 'executing',
                'source' => getControllerCategory(),
            ]);

            TaskJob::dispatch(['id' => $task->id])
                ->delay(now()->addSeconds(123))
                ->onQueue(config('queue.names.tasks'));
        }

        $this->success();
    }

    /**
     * 执行取消 — 延时任务调用，调 Api->cancel()，退费处理
     */
    public function cancel(int $acmeId): void
    {
        $acme = Acme::find($acmeId);

        if (! $acme) {
            $this->error('ACME 订单不存在');
        }

        if ($acme->status !== Acme::STATUS_CANCELLING) {
            $this->error('订单状态不是取消中');
        }

        // 调用上游取消
        if ($acme->api_id) {
            try {
                $result = (new Api)->cancel($acme->id);
            } catch (ApiResponseException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $this->error($e->getMessage());
            }

            // 上游返回吊销状态
            $status = $result['data']['status'] ?? '';
            if ($status === 'revoked') {
                $this->refund($acme);
                $acme->update(['status' => Acme::STATUS_REVOKED]);
                $this->success();
            }
        }

        // 退费并标记已取消
        $this->refund($acme);
        $acme->update(['status' => Acme::STATUS_CANCELLED]);

        $this->success();
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
     * 备注
     */
    public function remark(int $acmeId, string $remark, string $field = 'remark'): void
    {
        $acme = Acme::findOrFail($acmeId);
        $acme->update([$field => $remark]);
        $this->success();
    }

    /**
     * 创建订单（内部方法）
     */
    private function createOrder(array $params): Acme
    {
        $product = Product::where('id', $params['product_id'] ?? 0)
            ->where('product_type', Product::TYPE_ACME)
            ->first();

        if (! $product) {
            $this->error('产品不存在或不支持 ACME');
        }

        $period = (int) ($params['period'] ?? 0);
        if (! in_array($period, $product->periods)) {
            $this->error('无效的购买时长');
        }

        $standardCount = (int) ($params['purchased_standard_count'] ?? 0);
        $wildcardCount = (int) ($params['purchased_wildcard_count'] ?? 0);

        // 计算订单金额
        $amount = OrderUtil::getLatestCertAmount(
            ['user_id' => $params['user_id'], 'product_id' => $params['product_id'], 'period' => $period, 'purchased_standard_count' => 0, 'purchased_wildcard_count' => 0],
            ['standard_count' => $standardCount, 'wildcard_count' => $wildcardCount, 'action' => 'new'],
            $product->toArray()
        );

        return Acme::create([
            'user_id' => $params['user_id'],
            'product_id' => $params['product_id'],
            'brand' => $product->brand,
            'period' => $period,
            'purchased_standard_count' => $standardCount,
            'purchased_wildcard_count' => $wildcardCount,
            'refer_id' => bin2hex(random_bytes(16)),
            'amount' => $amount,
            'status' => Acme::STATUS_UNPAID,
            'remark' => $params['remark'] ?? null,
        ]);
    }

    /**
     * 支付订单（内部方法）
     */
    private function payOrder(Acme $acme): Acme
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
     * 提交订单到 Gateway（内部方法）
     */
    private function commitOrder(Acme $acme): Acme
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
        } catch (ApiResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
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
