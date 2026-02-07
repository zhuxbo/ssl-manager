<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiLog;
use App\Models\Order;
use App\Models\User;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ApiResponse;

    /**
     * 获取缓存时间（分钟）
     */
    private function getCacheMinutes(): int
    {
        return (int) get_system_setting('site', 'dashboardCache', 10);
    }

    /**
     * 获取管理端首页统计数据总览
     */
    public function overview(): void
    {
        $cacheKey = 'dashboard:admin:overview';
        $cacheMinutes = $this->getCacheMinutes();

        $data = Cache::remember($cacheKey, $cacheMinutes * 60, function () {
            return [
                'total_users' => User::count(),
                'total_orders' => Order::count(),
                'total_revenue' => Order::sum('amount'),
                'active_orders' => $this->getActiveOrdersCount(),
            ];
        });

        $this->success((array) $data);
    }

    /**
     * 获取系统概览统计
     */
    public function systemOverview(): void
    {
        $cacheKey = 'dashboard:admin:system_overview';
        $cacheMinutes = $this->getCacheMinutes();

        $data = Cache::remember($cacheKey, $cacheMinutes * 60, function () {
            $today = now()->startOfDay();
            $thisMonth = now()->startOfMonth();
            $thisWeekMonday = now()->startOfWeek();

            // 月度数据
            $monthlyData = [
                'total_users' => User::count(),
                'total_orders' => Order::count(),
                'active_orders' => $this->getActiveOrdersCount(),
                'expiring_orders' => Order::join('certs', 'orders.latest_cert_id', '=', 'certs.id')
                    ->where('certs.status', 'active')
                    ->whereBetween('certs.expires_at', [now(), now()->addDays(30)])
                    ->count(),
            ];

            // 获取今日API统计
            $todayApiStats = $this->getTodayApiStats();

            // 财务数据：日/周/月充值和消费
            $financeData = $this->getFinanceData($today, $thisWeekMonday, $thisMonth);

            // 新增用户统计：日/周/月（一条 SQL）
            // 跨月周场景下 $thisWeekMonday 可能早于 $thisMonth，取较早者避免周统计被截断
            $rangeStart = min($thisWeekMonday, $thisMonth);
            $userRow = User::where('created_at', '>=', $rangeStart)
                ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as monthly', [$thisMonth])
                ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as weekly', [$thisWeekMonday])
                ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as daily', [$today])
                ->first();
            $newUsers = [
                'daily' => (int) $userRow->daily,
                'weekly' => (int) $userRow->weekly,
                'monthly' => (int) $userRow->monthly,
            ];

            // 新增有效订单统计：日/周/月（一条 SQL）
            $orderRow = Order::where('orders.created_at', '>=', $rangeStart)
                ->whereHas('latestCert', function ($q) {
                    $q->whereIn('status', self::ACTIVATING_STATUSES);
                })
                ->selectRaw('SUM(CASE WHEN orders.created_at >= ? THEN 1 ELSE 0 END) as monthly', [$thisMonth])
                ->selectRaw('SUM(CASE WHEN orders.created_at >= ? THEN 1 ELSE 0 END) as weekly', [$thisWeekMonday])
                ->selectRaw('SUM(CASE WHEN orders.created_at >= ? THEN 1 ELSE 0 END) as daily', [$today])
                ->first();
            $newOrders = [
                'daily' => (int) $orderRow->daily,
                'weekly' => (int) $orderRow->weekly,
                'monthly' => (int) $orderRow->monthly,
            ];

            // 每日数据
            $dailyData = [
                'api_calls' => $todayApiStats['calls'],
                'api_errors' => $todayApiStats['errors'],
                'error_rate' => $todayApiStats['error_rate'],
                'version_stats' => $todayApiStats['version_stats'],
            ];

            return [
                'monthly' => $monthlyData,
                'daily' => $dailyData,
                'finance' => $financeData,
                'new_users' => $newUsers,
                'new_orders' => $newOrders,
            ];
        });

        $this->success((array) $data);
    }

    /**
     * 获取实时统计数据
     */
    public function realtime(): void
    {
        $cacheKey = 'dashboard:admin:realtime';
        $cacheMinutes = $this->getCacheMinutes();

        $realtimeStats = Cache::remember($cacheKey, $cacheMinutes * 60, function () {
            $today = now()->startOfDay();

            // 7天内到期订单数
            $expiring7Days = Order::join('certs', 'orders.latest_cert_id', '=', 'certs.id')
                ->where('certs.status', 'active')
                ->whereBetween('certs.expires_at', [now(), now()->addDays(7)])
                ->count();

            // 30天内到期订单数
            $expiring30Days = Order::join('certs', 'orders.latest_cert_id', '=', 'certs.id')
                ->where('certs.status', 'active')
                ->whereBetween('certs.expires_at', [now(), now()->addDays(30)])
                ->count();

            // 7天内签发订单数
            $issued7Days = Order::join('certs', 'orders.latest_cert_id', '=', 'certs.id')
                ->whereNotNull('certs.issued_at')
                ->whereBetween('certs.issued_at', [now()->subDays(7), now()])
                ->count();

            // 30天内签发订单数
            $issued30Days = Order::join('certs', 'orders.latest_cert_id', '=', 'certs.id')
                ->whereNotNull('certs.issued_at')
                ->whereBetween('certs.issued_at', [now()->subDays(30), now()])
                ->count();

            return [
                'online_users' => $this->getOnlineUsersCount(),
                'today' => [
                    'processing_orders' => Order::join('certs', 'orders.latest_cert_id', '=', 'certs.id')
                        ->whereIn('certs.status', ['pending', 'processing'])
                        ->count(),
                    'new_orders' => Order::whereDate('created_at', $today)->count(),
                    'new_users' => User::whereDate('created_at', $today)->count(),
                ],
                'alerts' => [
                    'expiring_7_days' => $expiring7Days,
                    'expiring_30_days' => $expiring30Days,
                    'issued_7_days' => $issued7Days,
                    'issued_30_days' => $issued30Days,
                ],
            ];
        });

        $this->success((array) $realtimeStats);
    }

    /**
     * 获取趋势数据
     */
    public function trends(Request $request): void
    {
        $days = min(max($request->input('days', 30), 7), 90);

        $cacheKey = "dashboard:admin:trends:$days";
        $cacheMinutes = $this->getCacheMinutes();

        $trends = Cache::remember($cacheKey, $cacheMinutes * 60, function () use ($days) {
            $startDate = now()->subDays($days - 1)->startOfDay();

            // 用户趋势（GROUP BY 聚合）
            $userCounts = User::where('created_at', '>=', $startDate)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as cnt')
                ->groupByRaw('DATE(created_at)')
                ->pluck('cnt', 'date');

            // 订单趋势（GROUP BY 聚合）
            $orderCounts = Order::where('created_at', '>=', $startDate)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as cnt')
                ->groupByRaw('DATE(created_at)')
                ->pluck('cnt', 'date');

            // 充值和消费趋势（一条 SQL 查出所有天数）
            $financeRows = DB::select("
                SELECT
                    DATE(created_at) as date,
                    COALESCE(SUM(CASE WHEN type IN ('addfunds', 'refunds', 'reverse') THEN amount ELSE 0 END), 0) AS recharge,
                    COALESCE(ABS(SUM(CASE WHEN type IN ('order', 'cancel', 'deduct') THEN amount ELSE 0 END)), 0) AS consumption
                FROM transactions
                WHERE created_at >= ?
                GROUP BY DATE(created_at)
            ", [$startDate]);

            $financeMap = [];
            foreach ($financeRows as $row) {
                $financeMap[$row->date] = [
                    'recharge' => round((float) $row->recharge, 2),
                    'consumption' => round((float) $row->consumption, 2),
                ];
            }

            // 组装结果
            $trends = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $dateStr = now()->subDays($i)->format('Y-m-d');
                $trends[] = [
                    'date' => $dateStr,
                    'users' => (int) ($userCounts[$dateStr] ?? 0),
                    'orders' => (int) ($orderCounts[$dateStr] ?? 0),
                    'recharge' => $financeMap[$dateStr]['recharge'] ?? 0.00,
                    'consumption' => $financeMap[$dateStr]['consumption'] ?? 0.00,
                ];
            }

            return $trends;
        });

        $this->success((array) $trends);
    }

    /**
     * 获取产品销售排行
     */
    public function topProducts(Request $request): void
    {
        $days = min(max($request->input('days', 30), 7), 90);
        $limit = min(max($request->input('limit', 10), 5), 50);

        $cacheKey = "dashboard:admin:top_products:$days:$limit";
        $cacheMinutes = $this->getCacheMinutes();

        $topProducts = Cache::remember($cacheKey, $cacheMinutes * 60, function () use ($days, $limit) {
            $startDate = now()->subDays($days);

            return Order::select('product_id')
                ->selectRaw('COUNT(*) as order_count')
                ->selectRaw('SUM(amount) as sales_amount')
                ->where('created_at', '>=', $startDate)
                ->groupBy('product_id')
                ->orderByDesc('sales_amount')
                ->limit($limit)
                ->with('product')
                ->get()
                ->map(function ($item) {
                    return [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product ? $item->product->name : '未知产品',
                        'order_count' => $item->order_count,
                        'sales_amount' => (float) $item->sales_amount,
                    ];
                })->toArray();
        });

        $this->success((array) $topProducts);
    }

    /**
     * 获取CA品牌统计
     */
    public function brandStats(Request $request): void
    {
        $days = min(max($request->input('days', 30), 7), 90);

        $cacheKey = "dashboard:admin:brand_stats:$days";
        $cacheMinutes = $this->getCacheMinutes();

        $brandStats = Cache::remember($cacheKey, $cacheMinutes * 60, function () use ($days) {
            $startDate = now()->subDays($days);

            // 基于产品的brand字段统计
            return Order::select('products.brand')
                ->selectRaw('COUNT(orders.id) as total_orders')
                ->selectRaw('SUM(orders.amount) as revenue')
                ->join('products', 'orders.product_id', '=', 'products.id')
                ->where('orders.created_at', '>=', $startDate)
                ->whereNotNull('products.brand')
                ->groupBy('products.brand')
                ->orderByDesc('revenue')
                ->get()
                ->map(function ($item) {
                    return [
                        'brand' => $item->brand,
                        'orders' => $item->total_orders,
                        'revenue' => (float) $item->revenue,
                    ];
                })->toArray();
        });

        $this->success((array) $brandStats);
    }

    /**
     * 获取用户等级分布
     */
    public function userLevelDistribution(): void
    {
        $cacheKey = 'dashboard:admin:user_level_distribution';
        $cacheMinutes = $this->getCacheMinutes();

        $distribution = Cache::remember($cacheKey, $cacheMinutes * 60, function () {
            return User::select('level_code')
                ->selectRaw('COUNT(*) as user_count')
                ->groupBy('level_code')
                ->get()
                ->map(function ($item) {
                    return [
                        'level_code' => $item->level_code,
                        'level_name' => $this->getLevelName($item->level_code),
                        'user_count' => $item->user_count,
                    ];
                })->toArray();
        });

        $this->success((array) $distribution);
    }

    /**
     * 获取系统健康状态
     */
    public function healthStatus(): void
    {
        $cacheKey = 'dashboard:admin:health_status';
        $cacheMinutes = min($this->getCacheMinutes(), 5); // 健康状态缓存时间不超过5分钟

        $data = Cache::remember($cacheKey, $cacheMinutes * 60, function () {
            $components = [
                'database' => $this->checkDatabaseStatus(),
                'cache' => $this->checkCacheStatus(),
                'queue' => $this->checkQueueStatus(),
                'storage' => $this->checkStorageStatus(),
            ];

            $overallStatus = collect($components)->every(fn ($s) => $s['status'] === 'healthy')
                ? 'healthy'
                : 'warning';

            return [
                'overall_status' => $overallStatus,
                'components' => $components,
            ];
        });

        $this->success((array) $data);
    }

    /**
     * 活动中的证书状态集（与订单搜索 activating 一致）
     */
    private const array ACTIVATING_STATUSES = ['pending', 'processing', 'active', 'approving'];

    /**
     * 获取有效订单数量
     */
    private function getActiveOrdersCount(): int
    {
        return Order::whereHas('latestCert', function ($q) {
            $q->whereIn('status', self::ACTIVATING_STATUSES);
        })->count();
    }

    /**
     * 获取财务数据：日/周/月的充值和消费
     */
    private function getFinanceData($today, $thisWeekMonday, $thisMonth): array
    {
        $prevDayStart = $today->copy()->subDay();
        $prevWeekStart = $thisWeekMonday->copy()->subWeek();
        $prevMonthStart = $thisMonth->copy()->subMonth();

        // 单次扫表，用 CASE WHEN 按日期阈值分桶
        $row = DB::selectOne("
            SELECT
                SUM(CASE WHEN created_at >= ? AND type IN ('addfunds','refunds','reverse') THEN amount ELSE 0 END) AS d_r,
                ABS(SUM(CASE WHEN created_at >= ? AND type IN ('order','cancel','deduct') THEN amount ELSE 0 END)) AS d_c,
                SUM(CASE WHEN created_at >= ? AND type IN ('addfunds','refunds','reverse') THEN amount ELSE 0 END) AS w_r,
                ABS(SUM(CASE WHEN created_at >= ? AND type IN ('order','cancel','deduct') THEN amount ELSE 0 END)) AS w_c,
                SUM(CASE WHEN created_at >= ? AND type IN ('addfunds','refunds','reverse') THEN amount ELSE 0 END) AS m_r,
                ABS(SUM(CASE WHEN created_at >= ? AND type IN ('order','cancel','deduct') THEN amount ELSE 0 END)) AS m_c,
                SUM(CASE WHEN created_at >= ? AND created_at < ? AND type IN ('addfunds','refunds','reverse') THEN amount ELSE 0 END) AS pd_r,
                ABS(SUM(CASE WHEN created_at >= ? AND created_at < ? AND type IN ('order','cancel','deduct') THEN amount ELSE 0 END)) AS pd_c,
                SUM(CASE WHEN created_at >= ? AND created_at < ? AND type IN ('addfunds','refunds','reverse') THEN amount ELSE 0 END) AS pw_r,
                ABS(SUM(CASE WHEN created_at >= ? AND created_at < ? AND type IN ('order','cancel','deduct') THEN amount ELSE 0 END)) AS pw_c,
                SUM(CASE WHEN created_at >= ? AND created_at < ? AND type IN ('addfunds','refunds','reverse') THEN amount ELSE 0 END) AS pm_r,
                ABS(SUM(CASE WHEN created_at >= ? AND created_at < ? AND type IN ('order','cancel','deduct') THEN amount ELSE 0 END)) AS pm_c
            FROM transactions
            WHERE created_at >= ?
        ", [
            $today, $today,
            $thisWeekMonday, $thisWeekMonday,
            $thisMonth, $thisMonth,
            $prevDayStart, $today, $prevDayStart, $today,
            $prevWeekStart, $thisWeekMonday, $prevWeekStart, $thisWeekMonday,
            $prevMonthStart, $thisMonth, $prevMonthStart, $thisMonth,
            $prevMonthStart,
        ]);

        $r = fn ($v) => round((float) ($v ?? 0), 2);

        return [
            'daily' => [
                'recharge' => $r($row->d_r), 'consumption' => $r($row->d_c),
                'prev_recharge' => $r($row->pd_r), 'prev_consumption' => $r($row->pd_c),
            ],
            'weekly' => [
                'recharge' => $r($row->w_r), 'consumption' => $r($row->w_c),
                'prev_recharge' => $r($row->pw_r), 'prev_consumption' => $r($row->pw_c),
            ],
            'monthly' => [
                'recharge' => $r($row->m_r), 'consumption' => $r($row->m_c),
                'prev_recharge' => $r($row->pm_r), 'prev_consumption' => $r($row->pm_c),
            ],
        ];
    }

    /**
     * 获取等级名称
     */
    private function getLevelName(string $levelCode): string
    {
        $levelNames = [
            'standard' => '标准会员',
            'gold' => '金牌会员',
            'platinum' => '铂金会员',
            'crown' => '皇冠会员',
            'partner' => '合作伙伴',
        ];

        return $levelNames[$levelCode] ?? '定制级别';
    }

    /**
     * 获取在线用户数量（近15分钟活跃）
     */
    private function getOnlineUsersCount(): int
    {
        $fifteenMinutesAgo = now()->subMinutes(15);

        return User::where('last_login_at', '>=', $fifteenMinutesAgo)->count();
    }

    /**
     * 获取今日API统计数据
     */
    private function getTodayApiStats(): array
    {
        $today = now()->startOfDay();

        // 总调用次数
        $totalCalls = ApiLog::whereDate('created_at', $today)->count();
        $errorCalls = ApiLog::whereDate('created_at', $today)
            ->where('status_code', '!=', 200)
            ->count();

        $errorRate = $totalCalls > 0 ? round(($errorCalls / $totalCalls) * 100, 2) : 0;

        // 按版本分组统计
        $versionStats = ApiLog::whereDate('created_at', $today)
            ->selectRaw('version, COUNT(*) as total_calls')
            ->selectRaw('SUM(CASE WHEN status_code = 200 THEN 1 ELSE 0 END) as success_calls')
            ->selectRaw('SUM(CASE WHEN status_code != 200 THEN 1 ELSE 0 END) as error_calls')
            ->groupBy('version')
            ->orderBy('total_calls', 'desc')
            ->get()
            ->map(function ($item) {
                $successRate = $item->total_calls > 0
                    ? round(($item->success_calls / $item->total_calls) * 100, 2)
                    : 0;

                return [
                    'version' => $item->version ?: '未知版本',
                    'total_calls' => $item->total_calls,
                    'success_calls' => $item->success_calls,
                    'error_calls' => $item->error_calls,
                    'success_rate' => $successRate,
                ];
            })
            ->toArray();

        return [
            'calls' => $totalCalls,
            'errors' => $errorCalls,
            'error_rate' => $errorRate,
            'version_stats' => $versionStats,
        ];
    }

    /**
     * 检查数据库状态
     */
    private function checkDatabaseStatus(): array
    {
        try {
            DB::connection()->getPdo();

            return ['status' => 'healthy', 'message' => '数据库连接正常'];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => '数据库连接失败: '.$e->getMessage()];
        }
    }

    /**
     * 检查缓存状态
     */
    private function checkCacheStatus(): array
    {
        try {
            Cache::put('health_check', 'ok', 10);
            $result = Cache::get('health_check');

            if ($result === 'ok') {
                return ['status' => 'healthy', 'message' => '缓存服务正常'];
            } else {
                return ['status' => 'warning', 'message' => '缓存服务异常'];
            }
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => '缓存服务失败: '.$e->getMessage()];
        }
    }

    /**
     * 检查队列状态
     */
    private function checkQueueStatus(): array
    {
        try {
            $queueDriver = config('queue.default');

            return match ($queueDriver) {
                'redis' => $this->checkRedisQueueStatus(),
                'database' => $this->checkDatabaseQueueStatus(),
                'sync' => ['status' => 'healthy', 'message' => '队列驱动为同步模式'],
                default => ['status' => 'warning', 'message' => "未知队列驱动: $queueDriver"],
            };
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => '队列服务检查失败: '.$e->getMessage()];
        }
    }

    /**
     * 检查Redis队列状态
     */
    private function checkRedisQueueStatus(): array
    {
        try {
            $redis = app('redis')->connection(config('queue.connections.redis.connection', 'default'));
            $queueName = config('queue.connections.redis.queue', 'default');

            // 检查Redis连接
            $redis->ping();

            // 获取队列长度
            $pendingJobs = $redis->llen("queues:$queueName");
            $delayedJobs = $redis->zcard("queues:$queueName:delayed");
            $reservedJobs = $redis->zcard("queues:$queueName:reserved");

            // 检查失败任务（仍然从数据库读取）
            $failedJobs = DB::table('failed_jobs')->count();

            if ($failedJobs > 100) {
                return [
                    'status' => 'warning',
                    'message' => "Redis队列正常，但有{$failedJobs}个失败任务 (待处理:$pendingJobs, 延迟:$delayedJobs, 保留:$reservedJobs)",
                ];
            }

            return [
                'status' => 'healthy',
                'message' => "Redis队列正常 (待处理:$pendingJobs, 延迟:$delayedJobs, 保留:$reservedJobs, 失败:$failedJobs)",
            ];

        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Redis队列服务失败: '.$e->getMessage()];
        }
    }

    /**
     * 检查数据库队列状态
     */
    private function checkDatabaseQueueStatus(): array
    {
        try {
            // 检查jobs表
            $pendingJobs = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();

            if ($failedJobs > 100) {
                return ['status' => 'warning', 'message' => "数据库队列正常，但有{$failedJobs}个失败任务"];
            }

            return ['status' => 'healthy', 'message' => "数据库队列正常，待处理:{$pendingJobs}，失败:$failedJobs"];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => '数据库队列服务失败: '.$e->getMessage()];
        }
    }

    /**
     * 检查存储状态
     */
    private function checkStorageStatus(): array
    {
        try {
            $path = storage_path('logs');

            if (! is_writable($path)) {
                return ['status' => 'error', 'message' => '存储目录不可写'];
            }

            $freeBytes = disk_free_space($path);
            $totalBytes = disk_total_space($path);
            $usedPercent = ($totalBytes - $freeBytes) / $totalBytes * 100;

            if ($usedPercent > 90) {
                return ['status' => 'warning', 'message' => sprintf('磁盘使用率过高: %.1f%%', $usedPercent)];
            }

            return ['status' => 'healthy', 'message' => sprintf('存储正常，使用率: %.1f%%', $usedPercent)];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => '存储检查失败: '.$e->getMessage()];
        }
    }

    /**
     * 清除Dashboard相关的所有缓存
     */
    public function clearCache(): void
    {
        try {
            // 定义需要清除的缓存键
            $cacheKeys = [
                'dashboard:admin:overview',
                'dashboard:admin:system_overview',
                'dashboard:admin:realtime',
                'dashboard:admin:user_level_distribution',
                'dashboard:admin:health_status',
            ];

            // 清除基础缓存
            foreach ($cacheKeys as $key) {
                Cache::forget($key);
            }

            // 清除趋势数据缓存（7-90天）
            for ($days = 7; $days <= 90; $days++) {
                Cache::forget("dashboard:admin:trends:$days");
            }

            // 清除产品排行缓存（7-90天，1-50限制）
            for ($days = 7; $days <= 90; $days++) {
                for ($limit = 1; $limit <= 50; $limit++) {
                    Cache::forget("dashboard:admin:top_products:$days:$limit");
                }
            }

            // 清除品牌统计缓存（7-90天）
            for ($days = 7; $days <= 90; $days++) {
                Cache::forget("dashboard:admin:brand_stats:$days");
            }
        } catch (Exception $e) {
            $this->error('缓存清除失败: '.$e->getMessage());
        }

        $this->success();
    }
}
