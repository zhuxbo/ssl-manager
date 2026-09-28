<?php

namespace App\Console\Commands;

use App\Models\AutoDeployReport;
use App\Services\Notification\SystemAlert;
use Illuminate\Console\Command;

/**
 * 自动部署 / 签发失败每日聚合告警。
 *
 * 失败事件只逐条写 auto_deploy_reports，不在请求/续签链路即时发信。本命令每天 0 点汇总前一个完整自然日的
 * failure 行，按订单分批发送 SystemAlert；每批独立去重，避免重跑重复发送。
 */
class DeployFailureReminderCommand extends Command
{
    // 每单单独占一个 details 字段；10 单加汇总字段不超过通用 Builder 的 20 字段上限。
    private const ORDERS_PER_EMAIL = 10;

    protected $signature = 'schedule:deploy-failure-reminder';

    protected $description = '按完整自然日聚合自动部署/签发失败并分批发送管理员告警';

    public function handle(): void
    {
        $windowEnd = now()->startOfDay();
        $windowStart = $windowEnd->copy()->subDay();
        $failures = AutoDeployReport::query()
            ->where('status', 'failure')
            ->where('created_at', '>=', $windowStart)
            ->where('created_at', '<', $windowEnd);
        $reportCount = (clone $failures)->count();

        if ($reportCount === 0) {
            $this->info("前一天无失败（{$windowStart->toDateTimeString()} - {$windowEnd->toDateTimeString()}）");

            return;
        }

        $orderCount = (clone $failures)->distinct()->count('order_id');
        $batchCount = (int) ceil($orderCount / self::ORDERS_PER_EMAIL);
        $batch = 0;
        (clone $failures)
            ->select('order_id')
            ->selectRaw('COUNT(*) AS report_count, SUM(CASE WHEN ip IS NOT NULL THEN 1 ELSE 0 END) AS client_failure_count')
            ->groupBy('order_id')
            ->orderBy('order_id')
            ->chunk(self::ORDERS_PER_EMAIL, function ($orders) use ($windowStart, $windowEnd, $reportCount, $orderCount, $batchCount, &$batch) {
                $batch++;
                $batchReportCount = (int) $orders->sum('report_count');
                $clientFailureCount = (int) $orders->sum('client_failure_count');
                $details = [
                    'report_count' => $batchReportCount,
                    'order_count' => $orders->count(),
                    'client_failure_count' => $clientFailureCount,
                    'server_failure_count' => $batchReportCount - $clientFailureCount,
                    'window_start' => $windowStart->toDateTimeString(),
                    'window_end' => $windowEnd->toDateTimeString(),
                    'batch' => $batch,
                    'batch_count' => $batchCount,
                ];
                foreach ($orders as $order) {
                    $details['订单_'.$order->order_id] = "失败 {$order->report_count} 条";
                }

                app(SystemAlert::class)->send(
                    'deploy_failure',
                    "自动部署/签发失败每日汇总（{$batch}/{$batchCount}）：{$batchReportCount} 条",
                    "{$windowStart->format('Y-m-d H:i')} 至 {$windowEnd->format('Y-m-d H:i')} 共 {$reportCount} 条失败，涉及 {$orderCount} 个订单。本封列出 {$orders->count()} 个订单，请在自动部署记录中查看详情。",
                    $details,
                    'deploy_failure_daily_'.$windowStart->format('Ymd')."_$batch",
                    48,
                    'deploy_failure_'.$windowStart->format('Ymd')."_$batch"
                );
            });

        $this->info("自动部署/签发失败每日汇总：{$reportCount} 条，{$orderCount} 个订单");
    }
}
