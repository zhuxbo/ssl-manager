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
            'quantity' => 'sometimes|integer|min:1|max:100',
        ]);

        $user = $this->guard->user();
        $billingService = app(BillingService::class);
        $quantity = (int) $request->input('quantity', 1);

        $created = 0;
        for ($i = 0; $i < $quantity; $i++) {
            $result = $billingService->createSubscription($user, $request->input('product_id'), $request->input('period'));
            if ($result['code'] !== 1) {
                if ($created === 0) {
                    $this->error($result['msg']);
                }
                break;
            }
            $created++;
        }

        $this->success(['created' => $created]);
    }

    /**
     * 查询 EAB 状态（按 orderId 精确查询）
     */
    public function getEab(Request $request, int $orderId): void
    {
        $order = Order::where('id', $orderId)
            ->where('user_id', $this->guard->id())
            ->whereNotNull('eab_kid')
            ->firstOrFail();

        $serverUrl = rtrim(get_system_setting('site', 'url', config('app.url')), '/').'/acme/directory';
        $configDir = "/etc/letsencrypt/$order->eab_kid";
        $configHome = "~/.acme.sh/$order->eab_kid";

        $data = [
            'eab_kid' => $order->eab_kid,
            'eab_hmac' => $order->eab_hmac,
            'eab_used' => $order->eab_used_at !== null,
            'server_url' => $serverUrl,
        ];

        $data['certbot_command'] = "certbot certonly --config-dir $configDir --server $serverUrl --eab-kid $order->eab_kid"
            ." --eab-hmac-key $order->eab_hmac"
            .' -d example.com --preferred-challenges dns-01';

        $data['acmesh_command'] = "acme.sh --register-account --config-home $configHome --server $serverUrl --eab-kid $order->eab_kid"
            ." --eab-hmac-key $order->eab_hmac";

        $this->success($data);
    }
}
