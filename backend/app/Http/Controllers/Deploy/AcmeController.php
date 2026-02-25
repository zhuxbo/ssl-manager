<?php

namespace App\Http\Controllers\Deploy;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Acme\BillingService;
use Illuminate\Http\Request;

class AcmeController extends Controller
{
    /**
     * 创建 ACME 订阅订单
     */
    public function createOrder(Request $request): void
    {
        $request->validate([
            'product_code' => 'required|string|max:50',
            'period' => 'required|integer',
        ]);

        $userId = $request->attributes->get('authenticated_user_id');

        if (! $userId) {
            $this->error('Unauthorized');
        }

        $product = Product::where('code', $request->input('product_code'))
            ->where('support_acme', 1)
            ->first();

        if (! $product) {
            $this->error('Product not found or does not support ACME');
        }

        $user = User::findOrFail($userId);
        $billingService = app(BillingService::class);

        $result = $billingService->createSubscription($user, $product->id, $request->input('period'));

        if ($result['code'] !== 1) {
            $this->error($result['msg']);
        }

        $order = $result['data']['order'];

        $this->success([
            'order_id' => $order->id,
            'eab_kid' => $result['data']['eab_kid'],
            'eab_hmac' => $result['data']['eab_hmac'],
            'server_url' => $result['data']['server_url'],
        ]);
    }

    /**
     * 按订单 ID 获取 EAB 凭据
     */
    public function getEab(Request $request, int $orderId): void
    {
        $userId = $request->attributes->get('authenticated_user_id');

        if (! $userId) {
            $this->error('Unauthorized');
        }

        $order = Order::where('id', $orderId)
            ->where('user_id', $userId)
            ->whereNotNull('eab_kid')
            ->first();

        if (! $order) {
            $this->error('No available EAB credentials for this order.');
        }

        $serverUrl = rtrim(get_system_setting('site', 'url', config('app.url')), '/').'/acme/directory';

        $this->success([
            'eab_kid' => $order->eab_kid,
            'eab_hmac' => $order->eab_hmac,
            'server_url' => $serverUrl,
        ]);
    }
}
