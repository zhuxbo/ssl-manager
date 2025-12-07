<?php

namespace App\Console\Commands;

use App\Models\AdminLog;
use App\Models\ApiLog;
use App\Models\CallbackLog;
use App\Models\CaLog;
use App\Models\EasyLog;
use App\Models\ErrorLog;
use App\Models\Fund;
use App\Models\Order;
use App\Models\UserLog;
use App\Services\Order\Action;
use Illuminate\Console\Command;
use Throwable;

class PurgeCommand extends Command
{
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

        // 清理超过180天的简易申请日志
        $result = EasyLog::where('created_at', '<', now()->subDays(180))->delete();
        $this->info("Purged $result easy logs");

        // 清理超过30天的GET方法简易申请日志
        $result = EasyLog::where('created_at', '<', now()->subDays(30))->whereIn('method', ['GET', 'OPTIONS'])->delete();
        $this->info("Purged $result GET easy logs");

        // 清理超过180天的CA日志
        $result = CaLog::where('created_at', '<', now()->subDays(180))->delete();
        $this->info("Purged $result ca logs");

        // 清理超过90天的错误日志
        $result = ErrorLog::where('created_at', '<', now()->subDays(90))->delete();
        $this->info("Purged $result error logs");

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
            foreach ($orders as $order) {
                try {
                    // 直接设置证书状态为取消中
                    $order->latestCert->update(['status' => 'cancelling']);

                    $action = new Action;

                    // 删除相关任务
                    $action->deleteTask($order->id, 'commit,sync,revalidate');

                    // 创建取消任务
                    $action->createTask($order->id, 'cancel');

                    $canceledCount++;
                } catch (Throwable $e) {
                    $this->error("Failed to cancel order $order->id: ".$e->getMessage());
                }
            }

            $this->info("Set $canceledCount orders to cancelling status: ".$orders->pluck('id')->implode(','));
        } else {
            $this->info('No orders to cancel near refund deadline');
        }
    }
}
