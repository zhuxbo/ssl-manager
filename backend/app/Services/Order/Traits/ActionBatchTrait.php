<?php

declare(strict_types=1);

namespace App\Services\Order\Traits;

use App\Models\Order;
use App\Models\Task;
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
            ->whereRaw('orders.created_at > DATE_SUB(NOW(), INTERVAL products.refund_period DAY)')
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
                $this->cancelPending($order->id);
            } else {
                // 2分钟后取消
                $order->latestCert->update(['status' => 'cancelling']);
                $this->deleteTask($order->id, 'commit,sync,revalidate');
                $this->createTask($order->id, 'cancel');
            }
        }

        $this->success();
    }

    /**
     * 批量撤销取消订单
     */
    public function batchRevokeCancel(int|string|array $orderIds): void
    {
        $orderIds = is_array($orderIds) ? $orderIds : explode(',', (string) $orderIds);
        $orderIds = array_map('intval', $orderIds);

        $orders = Order::with(['latestCert'])
            ->whereHas('latestCert', fn ($query) => $query->where('status', 'cancelling'))
            ->whereIn('id', $orderIds)
            ->get();

        if ($orders->isEmpty()) {
            $this->error('没有可以撤销的订单');
        }

        foreach ($orders as $order) {
            $this->deleteTask($order->id, 'cancel');
            $order->latestCert->update(['status' => 'approving']);
            $this->createTask($order->id, 'sync');
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
