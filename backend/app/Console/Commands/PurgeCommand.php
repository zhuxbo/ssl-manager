<?php

namespace App\Console\Commands;

use App\Exceptions\ApiResponseException;
use App\Models\AdminLog;
use App\Models\ApiLog;
use App\Models\CallbackLog;
use App\Models\CaLog;
use App\Models\ErrorLog;
use App\Models\Fund;
use App\Models\Order;
use App\Models\Task;
use App\Models\UserLog;
use App\Services\Order\Action;
use Illuminate\Console\Command;
use Throwable;

class PurgeCommand extends Command
{
    private const SYNC_INTERVAL_HOURS = 24;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schedule:purge';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge cache, logs, or other unnecessary data';

    /**
     * Execute the console command.
     *
     * @throws Throwable
     */
    public function handle(): void
    {
        $this->info(get_system_setting('site', 'name', 'SSL证书管理系统'));

        // 清理超过24小时的未支付充值
        $result = Fund::where('created_at', '<', now()->subHours(24))->where('status', 0)->delete();
        $this->info("Purged $result fund records");

        // 清理超过180天的接口日志
        $result = ApiLog::where('created_at', '<', now()->subDays(180))->delete();
        $this->info("Purged $result API logs");

        // 清理超过30天的GET方法接口日志
        $result = ApiLog::where('created_at', '<', now()->subDays(30))->where('method', 'GET')->delete();
        $this->info("Purged $result GET API logs");

        // 清理超过180天的管理员日志
        $result = AdminLog::where('created_at', '<', now()->subDays(180))->delete();
        $this->info("Purged $result admin logs");

        // 清理超过30天的GET方法管理员日志
        $result = AdminLog::where('created_at', '<', now()->subDays(30))->whereIn('method', ['GET', 'OPTIONS'])->delete();
        $this->info("Purged $result GET admin logs");

        // 清理超过180天的用户日志
        $result = UserLog::where('created_at', '<', now()->subDays(180))->delete();
        $this->info("Purged $result user logs");

        // 清理超过30天的GET方法用户日志
        $result = UserLog::where('created_at', '<', now()->subDays(30))->whereIn('method', ['GET', 'OPTIONS'])->delete();
        $this->info("Purged $result GET user logs");

        // 清理超过180天的回调日志
        $result = CallbackLog::where('created_at', '<', now()->subDays(180))->delete();
        $this->info("Purged $result callback logs");

        // 清理超过180天的CA日志
        $result = CaLog::where('created_at', '<', now()->subDays(180))->delete();
        $this->info("Purged $result ca logs");

        // 清理超过90天的错误日志
        $result = ErrorLog::where('created_at', '<', now()->subDays(90))->delete();
        $this->info("Purged $result error logs");

        // 动态清理其他 _logs 后缀表（插件日志表等）
        $knownLogTables = ['api_logs', 'admin_logs', 'user_logs', 'callback_logs', 'ca_logs', 'error_logs'];
        try {
            $rows = \Illuminate\Support\Facades\DB::select("SHOW TABLES LIKE '%\\_logs'");
            foreach ($rows as $row) {
                $tableName = current((array) $row);
                if (! preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
                    continue;
                }
                if (in_array($tableName, $knownLogTables)) {
                    continue;
                }
                $result = \Illuminate\Support\Facades\DB::table($tableName)->where('created_at', '<', now()->subDays(180))->delete();
                $this->info("Purged $result $tableName");
            }
        } catch (\Throwable $e) {
            $this->warn("Dynamic log cleanup failed: ".$e->getMessage());
        }

        // 预同步：距退款期限2-4天的处理中订单，24小时内无同步则创建sync任务
        $preSyncOrders = Order::with(['latestCert'])
            ->join('products', 'orders.product_id', '=', 'products.id')
            ->whereHas('latestCert', fn ($query) => $query->where('status', 'processing'))
            ->whereRaw('orders.created_at <= DATE_SUB(NOW(), INTERVAL products.refund_period - 4 DAY)')
            ->whereRaw('orders.created_at > DATE_SUB(NOW(), INTERVAL products.refund_period - 2 DAY)')
            ->select('orders.*')
            ->get();

        $preSyncCount = 0;
        foreach ($preSyncOrders as $order) {
            if (! $this->hasRecentSyncAttempt($order->id)) {
                $action = new Action;
                $action->createTask($order->id, 'sync');
                $preSyncCount++;
            }
        }
        $this->info("Pre-sync created for $preSyncCount orders");

        // 取消临近退款期限的处理中订单（还剩2天内的订单）
        $orders = Order::with(['latestCert'])
            ->join('products', 'orders.product_id', '=', 'products.id')
            ->whereHas('latestCert', fn ($query) => $query->where('status', 'processing'))
            ->whereRaw('orders.created_at > DATE_SUB(NOW(), INTERVAL products.refund_period DAY)')
            ->whereRaw('orders.created_at <= DATE_SUB(NOW(), INTERVAL products.refund_period - 2 DAY)')
            ->select('orders.*')
            ->get();

        if ($orders->isNotEmpty()) {
            $canceledCount = 0;
            $action = new Action;

            foreach ($orders as $order) {
                try {
                    // 取消前执行即时同步
                    if (! $this->syncImmediately($action, $order)) {
                        $this->info("Order $order->id: sync error, skip cancel");

                        continue;
                    }

                    // 刷新证书状态
                    $order->latestCert->refresh();
                    if ($order->latestCert->status !== 'processing') {
                        $this->info("Order $order->id: status changed to $order->latestCert->status after sync, skip cancel");

                        continue;
                    }

                    // 仍是processing，执行取消
                    $order->latestCert->update(['status' => 'cancelling']);

                    // 删除相关任务
                    $action->deleteTask($order->id, 'commit,sync,revalidate');

                    // 创建取消任务
                    $action->createTask($order->id, 'cancel');

                    $canceledCount++;
                } catch (Throwable $e) {
                    $this->error("Failed to process order $order->id: ".$e->getMessage());
                }
            }

            $this->info("Set $canceledCount orders to cancelling status: ".$orders->pluck('id')->implode(','));
        } else {
            $this->info('No orders to cancel near refund deadline');
        }
    }

    /**
     * 判断是否在同步间隔内有过同步记录。
     */
    private function hasRecentSyncAttempt(int $orderId): bool
    {
        $threshold = now()->subHours(self::SYNC_INTERVAL_HOURS);

        return Task::where('order_id', $orderId)
            ->where('action', 'sync')
            ->where(function ($query) use ($threshold) {
                $query->where('started_at', '>=', $threshold)
                    ->orWhere('last_execute_at', '>=', $threshold);
            })
            ->exists();
    }

    /**
     * 立即执行同步并记录结果，force 模式下成功时静默返回不抛异常。
     */
    private function syncImmediately(Action $action, Order $order): bool
    {
        try {
            $action->sync($order->id, true);

            return true;
        } catch (ApiResponseException $e) {
            $result = $e->getApiResponse();
            $status = ($result['code'] ?? 0) === 1 ? 'successful' : 'failed';
            $this->recordSyncAttempt($order->id, $result, $status);

            return $status === 'successful';
        } catch (Throwable $e) {
            $this->recordSyncAttempt($order->id, [
                'code' => 0,
                'msg' => $e->getMessage(),
            ], 'failed');

            return false;
        }
    }

    /**
     * 记录一次即时同步的结果，避免推送队列任务。
     */
    private function recordSyncAttempt(int $orderId, array $result, string $status): void
    {
        Task::create([
            'order_id' => $orderId,
            'action' => 'sync',
            'result' => $result,
            'attempts' => 1,
            'started_at' => now(),
            'last_execute_at' => now(),
            'source' => getControllerCategory(),
            'weight' => 0,
            'status' => $status,
        ]);
    }
}
