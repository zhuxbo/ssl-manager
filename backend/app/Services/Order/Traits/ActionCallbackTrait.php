<?php

declare(strict_types=1);

namespace App\Services\Order\Traits;

use App\Models\Callback;
use App\Models\Order;
use Illuminate\Support\Facades\Http;

trait ActionCallbackTrait
{
    /**
     * 检查 URL 是否指向私有/内网地址（SSRF 防护）
     */
    private function isPrivateUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! $host) {
            return true;
        }

        $ip = gethostbyname($host);
        // gethostbyname 解析失败时返回原始主机名
        if ($ip === $host && ! filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }

        return ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

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

        // SSRF 防护：检查回调 URL 是否指向私有/内网地址
        if ($this->isPrivateUrl($callback->url)) {
            $this->error('回调地址不允许指向内网');
        }

        // 考虑回调提供订单数据
        $response = Http::asForm()->post($callback->url, [
            'id' => $orderId,
            'token' => $callback->token,
        ]);

        $httpCode = $response->status();

        // 状态码是 200 就认为回调成功
        if ($httpCode !== 200) {
            // TODO: 如果回调提供订单数据 则可以限制在 updated_at 5分钟内 每分钟尝试重新回调
            $this->error('Http Status '.$httpCode);
        }

        $this->success();
    }
}
