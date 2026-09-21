<?php

declare(strict_types=1);

namespace App\Services\Order\Traits;

use App\Models\Order;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Throwable;

trait ActionBatchTrait
{
    /**
     * 批量提交订单
     */
    public function batchCommit(int|string|array $orderIds): void
    {
        $orderIds = is_array($orderIds) ? $orderIds : explode(',', (string) $orderIds);
        $orderIds = array_map('intval', $orderIds);

        $commitIds = Order::with(['latestCert'])
            ->whereHas('latestCert', fn ($query) => $query->where('status', 'pending'))
            ->whereIn('id', $orderIds)
            ->pluck('id')
            ->all();

        if (empty($commitIds)) {
            $this->error('没有可以提交的订单');
        }

        $this->checkRepeat($commitIds, 'commit');

        $this->createTask($commitIds, 'commit');
        $this->success();
    }

    /**
     * 批量执行验证
     */
    public function batchRevalidate(int|string|array $orderIds): void
    {
        $orderIds = is_array($orderIds) ? $orderIds : explode(',', (string) $orderIds);
        $orderIds = array_map('intval', $orderIds);

        $revalidateIds = Order::with(['latestCert'])
            ->whereHas('latestCert', fn ($query) => $query->where('domain_verify_status', '<>', 2)->where('status', 'processing'))
            ->whereIn('id', $orderIds)
            ->pluck('id')
            ->all();

        if (empty($revalidateIds)) {
            $this->error('没有可以验证的订单');
        }

        $this->checkRepeat($revalidateIds, 'revalidate');

        $this->createTask($revalidateIds, 'revalidate');
        $this->success();
    }

    /**
     * 批量同步订单
     */
    public function batchSync(int|string|array $orderIds): void
    {
        $orderIds = is_array($orderIds) ? $orderIds : explode(',', (string) $orderIds);
        $orderIds = array_map('intval', $orderIds);

        $syncIds = Order::with(['latestCert'])
            ->whereHas('latestCert', fn ($query) => $query->whereIn('status', ['processing', 'active', 'approving']))
            ->whereIn('id', $orderIds)
            ->pluck('id')
            ->all();

        if (empty($syncIds)) {
            $this->error('没有可以同步的订单');
        }

        $this->checkRepeat($syncIds, 'sync');

        $this->createTask($syncIds, 'sync');
        $this->success();
    }

    /**
     * 批量取消订单
     *
     * @throws Throwable
     */
    public function batchCommitCancel(int|string|array $orderIds): void
    {
        $orderIds = is_array($orderIds) ? $orderIds : explode(',', (string) $orderIds);
        $orderIds = array_map('intval', $orderIds);

        $maxUpstream = (int) config('batch.max_upstream');
        count($orderIds) > $maxUpstream && $this->error("订单数量不能超过{$maxUpstream}");

        // 首先查询状态为unpaid或pending的订单
        $unpaidOrPendingOrders = Order::with(['product', 'latestCert'])
            ->whereHas('product')
            ->whereHas('latestCert', fn ($query) => $query->whereIn('status', ['unpaid', 'pending']))
            ->whereIn('id', $orderIds)
            ->get();

        // 然后查询状态为processing, active, approving且在退款期内的订单
        $refundableOrders = Order::with(['product', 'latestCert'])
            ->join('products', 'orders.product_id', '=', 'products.id')
            ->whereHas('latestCert', fn ($query) => $query->whereIn('status', ['processing', 'active', 'approving']))
            ->where('orders.created_at', '>', DB::raw('DATE_SUB(NOW(), INTERVAL products.refund_period DAY)'))
            ->whereIn('orders.id', $orderIds)
            ->select('orders.*')
            ->get();

        // 合并两个结果集
        $orders = $unpaidOrPendingOrders->concat($refundableOrders);

        if ($orders->isEmpty()) {
            $this->error('没有可以取消的订单');
        }

        foreach ($orders as $order) {
            if ($order->latestCert->status === 'unpaid') {
                $this->delete($order->id);
            } elseif ($order->latestCert->status === 'pending') {
                // cancelPending 自带事务 + 行锁 + 锁内 status 校验
                $this->cancelPending($order->id);
            } else {
                // processing/approving/active 分支：事务 + 行锁 + 锁内 status 二次校验，
                // 与单体 commitCancel 的 active 分支同构，防止与 cancel TaskJob/archive 竞态
                $this->runTaskMutationTransaction(function () use ($order) {
                    // 锁顺序 1：先锁 commit/sync/revalidate task（与 TaskJob::handle 的 task→order 顺序一致）
                    Task::lockForMutation($order->id, ['commit', 'sync', 'revalidate'])->get();

                    // 锁顺序 2：再锁 order
                    $locked = Order::with(['latestCert'])
                        ->whereHas('latestCert')
                        ->lock()
                        ->find($order->id);

                    if (! $locked) {
                        return;
                    }

                    if (! in_array($locked->latestCert->status, ['processing', 'approving', 'active'])) {
                        // 锁内发现状态已变，静默跳过（批量场景不让单条状态漂移破坏全批）
                        return;
                    }

                    $locked->latestCert->update(['status' => 'cancelling']);
                    $this->deleteTask($locked->id, 'commit,sync,revalidate');
                    $this->createTask($locked->id, 'cancel');
                });
            }
        }

        $this->success();
    }

    /**
     * 检查是否存在重复任务
     */
    protected function checkRepeat(array $orderIds, string $action): void
    {
        $tasks = Task::where('action', $action)
            ->whereIn('order_id', $orderIds)
            ->where('status', 'executing')
            ->exists();

        if ($tasks) {
            $this->error('已存在处理中的任务，请稍后刷新页面');
        }
    }
}
