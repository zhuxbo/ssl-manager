<?php

declare(strict_types=1);

namespace App\Http\Controllers\Callback;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Order\Action;
use Illuminate\Http\Request;

class SslController extends Controller
{
    public function index(Request $request): void
    {
        if (! $this->isIpAllowed($request->ip())) {
            $this->error('IP not allowed');
        }

        $callback_token = get_system_setting('site', 'callbackToken');

        $token = $request->input('token')
            ?? $request->input('password')
            ?? $request->server('TOKEN')
            ?? '';

        if ($token !== $callback_token) {
            $this->error('Invalid token');
        }

        $api_id = $request->input('id') ?? $request->input('orderId') ?? '';
        $order = Order::with(['latestCert'])
            ->whereHas('latestCert', function ($query) use ($api_id) {
                $query->where('api_id', $api_id);
            })
            ->first();

        if (! $order) {
            $this->error('Order not found');
        }

        if (in_array($order->latestCert->status, ['processing', 'active', 'approving'])) {
            (new Action)->createTask($order->id, 'sync');
        }

        $this->success();
    }

    /**
     * 检查IP是否允许回调
     */
    private function isIpAllowed(string $ip): bool
    {
        $callback_allowed_ips = get_system_setting('site', 'callbackAllowedIps');

        if (empty($callback_allowed_ips)) {
            return true;
        }

        return in_array($ip, $callback_allowed_ips);
    }
}
