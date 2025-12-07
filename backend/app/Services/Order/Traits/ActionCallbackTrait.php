<?php

declare(strict_types=1);

namespace App\Services\Order\Traits;

use App\Models\Callback;
use App\Models\Order;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

trait ActionCallbackTrait
{
    /**
     * @throws GuzzleException
     */
    public function callback(int $orderId): void
    {
        $order = Order::with(['latestCert'])
            ->whereHas('latestCert', function ($query) {
                $query->whereIn('status', ['active', 'cancelled', 'revoked']);
            })
            ->where('id', $orderId)
            ->first();

        if (! $order) {
            $this->error('订单不存在');
        }

        $callback = Callback::where('user_id', $order->user_id)->where('status', 1)->first();

        if (! $callback) {
            $this->error('用户未启用回调');
        }

        // 考虑回调提供订单数据
        $response = (new Client)->post($callback->url, [
            'form_params' => [
                'id' => $orderId,
                'token' => $callback->token,
            ],
        ]);

        $httpCode = $response->getStatusCode();

        // 状态码是 200 就认为回调成功
        if ($httpCode !== 200) {
            // TODO: 如果回调提供订单数据 则可以限制在 updated_at 5分钟内 每分钟尝试重新回调
            $this->error('Http Status '.$httpCode);
        }

        $this->success();
    }
}
