<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use App\Models\User;
use App\Services\Acme\BillingService;
use Illuminate\Http\Request;

class AcmeController extends BaseController
{
    /**
     * 创建 ACME 订阅订单
     */
    public function createOrder(Request $request): void
    {
        $request->validate([
            'user_id' => 'required|integer',
            'product_id' => 'required|integer',
            'period' => 'required|integer',
        ]);

        $user = User::findOrFail($request->input('user_id'));
        $billingService = app(BillingService::class);

        $result = $billingService->createSubscription($user, $request->input('product_id'), $request->input('period'));

        if ($result['code'] !== 1) {
            $this->error($result['msg']);
        }

        $order = $result['data']['order'];
        $serverUrl = $result['data']['server_url'];
        $eabKid = $result['data']['eab_kid'];
        $eabHmac = $result['data']['eab_hmac'];

        $this->success([
            'order_id' => $order->id,
            'eab_kid' => $eabKid,
            'eab_hmac' => $eabHmac,
            'server_url' => $serverUrl,
            'certbot_command' => "certbot certonly --server $serverUrl --eab-kid $eabKid"
                ." --eab-hmac-key $eabHmac"
                .' -d example.com --preferred-challenges dns-01',
            'acmesh_command' => "acme.sh --register-account --server $serverUrl --eab-kid $eabKid"
                ." --eab-hmac-key $eabHmac",
        ]);
    }

    /**
     * 获取订单 EAB 信息
     */
    public function getEab(Request $request, int $orderId): void
    {
        $order = Order::where('id', $orderId)
            ->whereNotNull('eab_kid')
            ->first();

        if (! $order) {
            $this->error('订单不存在或无 EAB 凭据');
        }

        $serverUrl = rtrim(get_system_setting('site', 'url', config('app.url')), '/').'/acme/directory';

        $this->success([
            'order_id' => $order->id,
            'eab_kid' => $order->eab_kid,
            'eab_hmac' => $order->eab_hmac,
            'eab_used' => $order->eab_used_at !== null,
            'server_url' => $serverUrl,
            'certbot_command' => "certbot certonly --server $serverUrl --eab-kid $order->eab_kid"
                ." --eab-hmac-key $order->eab_hmac"
                .' -d example.com --preferred-challenges dns-01',
            'acmesh_command' => "acme.sh --register-account --server $serverUrl --eab-kid $order->eab_kid"
                ." --eab-hmac-key $order->eab_hmac",
        ]);
    }
}
