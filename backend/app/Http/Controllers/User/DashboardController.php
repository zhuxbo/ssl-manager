<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Traits\ApiResponse;
use Cache;
use Illuminate\Http\Request;

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
     * 获取首页统计数据总览
     */
    public function overview(): void
    {
        $userId = auth('user')->id();
        $user = User::find($userId);

        if (! $user) {
            $this->error('用户不存在');
        }

        $cacheKey = "dashboard:user:$userId:overview";
        $cacheMinutes = $this->getCacheMinutes();

        $data = Cache::remember($cacheKey, $cacheMinutes * 60, function () use ($userId, $user) {
            return [
                'user_info' => $user->only(['username', 'email', 'mobile']),
                'assets' => $this->getAssetsData($userId),
                'orders' => $this->getOrdersData($userId),
            ];
        });

        $this->success((array) $data);
    }

    /**
     * 获取资产统计
     */
    public function assets(): void
    {
        $userId = auth('user')->id();

        $cacheKey = "dashboard:user:$userId:assets";
        $cacheMinutes = $this->getCacheMinutes();

        $data = Cache::remember($cacheKey, $cacheMinutes * 60, function () use ($userId) {
            return $this->getAssetsData($userId);
        });

        $this->success((array) $data);
    }

    /**
     * 获取订单统计
     */
    public function orders(): void
    {
        $userId = auth('user')->id();

        $cacheKey = "dashboard:user:$userId:orders";
        $cacheMinutes = $this->getCacheMinutes();

        $data = Cache::remember($cacheKey, $cacheMinutes * 60, function () use ($userId) {
            return $this->getOrdersData($userId);
        });

        $this->success((array) $data);
    }

    /**
     * 获取趋势数据
     */
    public function trend(Request $request): void
    {
        $userId = auth('user')->id();
        $days = min(max($request->input('days', 30), 7), 90);

        $cacheKey = "dashboard:user:$userId:trend:$days";
        $cacheMinutes = $this->getCacheMinutes();

        $trends = Cache::remember($cacheKey, $cacheMinutes * 60, function () use ($userId, $days) {
            $trends = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $dateStr = $date->format('Y-m-d');

                $trends[] = [
                    'date' => $dateStr,
                    'orders' => Order::where('user_id', $userId)
                        ->whereDate('created_at', $date)
                        ->count(),
                    'consumption' => (float) Order::where('user_id', $userId)
                        ->whereDate('created_at', $date)
                        ->sum('amount'),
                ];
            }

            return $trends;
        });

        $this->success((array) $trends);
    }

    /**
     * 获取月度统计对比
     */
    public function monthlyComparison(): void
    {
        $userId = auth('user')->id();

        $cacheKey = "dashboard:user:$userId:monthly_comparison";
        $cacheMinutes = $this->getCacheMinutes();

        $comparison = Cache::remember($cacheKey, $cacheMinutes * 60, function () use ($userId) {
            $currentMonth = now()->startOfMonth();
            $lastMonth = $currentMonth->copy()->subMonth();

            $currentOrders = Order::where('user_id', $userId)
                ->whereMonth('created_at', $currentMonth->month)
                ->whereYear('created_at', $currentMonth->year)
                ->count();

            $currentConsumption = Order::where('user_id', $userId)
                ->whereMonth('created_at', $currentMonth->month)
                ->whereYear('created_at', $currentMonth->year)
                ->sum('amount');

            $lastOrders = Order::where('user_id', $userId)
                ->whereMonth('created_at', $lastMonth->month)
                ->whereYear('created_at', $lastMonth->year)
                ->count();

            $lastConsumption = Order::where('user_id', $userId)
                ->whereMonth('created_at', $lastMonth->month)
                ->whereYear('created_at', $lastMonth->year)
                ->sum('amount');

            return [
                'current_month' => [
                    'orders' => $currentOrders,
                    'consumption' => (float) $currentConsumption,
                ],
                'last_month' => [
                    'orders' => $lastOrders,
                    'consumption' => (float) $lastConsumption,
                ],
                'growth' => [
                    'orders' => $this->calculateGrowth($lastOrders, $currentOrders),
                    'consumption' => $this->calculateGrowth($lastConsumption, $currentConsumption),
                ],
            ];
        });

        $this->success((array) $comparison);
    }

    /**
     * 获取资产数据
     */
    private function getAssetsData($userId): array
    {
        $user = User::find($userId);

        if (! $user) {
            return [
                'balance' => 0.0,
            ];
        }

        return [
            'balance' => (float) ($user->balance ?? 0),
        ];
    }

    /**
     * 获取订单数据（包含订单状态分布）
     */
    private function getOrdersData($userId): array
    {
        $totalOrders = Order::where('user_id', $userId)->count();

        // 有效订单：通过订单表联查latestCert，状态为active的订单
        $activeOrders = Order::where('user_id', $userId)
            ->join('certs', 'orders.latest_cert_id', '=', 'certs.id')
            ->whereIn('certs.status', ['active'])
            ->count();

        // 7天内到期订单数
        $expiring7Days = Order::where('user_id', $userId)
            ->join('certs', 'orders.latest_cert_id', '=', 'certs.id')
            ->where('certs.status', 'active')
            ->whereBetween('certs.expires_at', [now(), now()->addDays(7)])
            ->count();

        // 30天内到期订单数
        $expiring30Days = Order::where('user_id', $userId)
            ->join('certs', 'orders.latest_cert_id', '=', 'certs.id')
            ->where('certs.status', 'active')
            ->whereBetween('certs.expires_at', [now(), now()->addDays(30)])
            ->count();

        // 使用cancelled_at字段判断取消的订单
        $cancelledOrders = Order::where('user_id', $userId)
            ->whereNotNull('cancelled_at')
            ->count();

        // 按latestCert状态统计订单分布
        $statusStats = Order::where('user_id', $userId)
            ->join('certs', 'orders.latest_cert_id', '=', 'certs.id')
            ->select('certs.status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('certs.status')
            ->pluck('count', 'status')
            ->toArray();

        // 本月订单和消费
        $monthlyOrders = Order::where('user_id', $userId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $monthlyConsumption = Order::where('user_id', $userId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        return [
            'total_orders' => $totalOrders,
            'active_orders' => $activeOrders,
            'expiring_7_days' => $expiring7Days,
            'expiring_30_days' => $expiring30Days,
            'cancelled_orders' => $cancelledOrders,
            'status_distribution' => $statusStats,
            'monthly_orders' => $monthlyOrders,
            'monthly_consumption' => (float) $monthlyConsumption,
        ];
    }

    /**
     * 计算增长率
     */
    private function calculateGrowth($lastValue, $currentValue): float
    {
        if ($lastValue == 0) {
            return $currentValue > 0 ? 100 : 0;
        }

        return round((($currentValue - $lastValue) / $lastValue) * 100, 2);
    }
}
