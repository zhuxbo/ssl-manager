<?php

namespace App\Http\Controllers\User;

use App\Models\Order;
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
            'product_id' => 'required|integer',
            'period' => 'required|integer',
        ]);

        $user = $this->guard->user();
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
     * 查询 EAB 状态
     */
    public function getEab(Request $request): void
    {
        $userId = $this->guard->id();

        $order = Order::where('user_id', $userId)
            ->whereHas('product', fn ($q) => $q->where('support_acme', 1))
            ->where('period_till', '>', now())
            ->whereNull('cancelled_at')
            ->whereNotNull('eab_kid')
            ->orderBy('period_till', 'desc')
            ->first();

        if (! $order) {
            $this->error('No ACME subscription found');
        }

        $serverUrl = rtrim(get_system_setting('site', 'url', config('app.url')), '/').'/acme/directory';

        $data = [
            'eab_kid' => $order->eab_kid,
            'eab_hmac' => $order->eab_hmac,
            'eab_used' => $order->eab_used_at !== null,
            'server_url' => $serverUrl,
        ];

        // 附加一键命令
        $data['certbot_command'] = "certbot certonly --server $serverUrl --eab-kid $order->eab_kid"
            ." --eab-hmac-key $order->eab_hmac"
            .' -d example.com --preferred-challenges dns-01';

        $data['acmesh_command'] = "acme.sh --register-account --server $serverUrl --eab-kid $order->eab_kid"
            ." --eab-hmac-key $order->eab_hmac";

        $this->success($data);
    }
}
