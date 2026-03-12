<?php

namespace App\Http\Controllers\Deploy;

use App\Http\Controllers\Controller;
use App\Models\Acme\AcmeOrder;
use App\Models\Product;
use App\Models\User;
use App\Services\Acme\BillingService;
use App\Services\Acme\OrderService;
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
            'domains' => 'required|string|max:5000',
            'validation_method' => 'required|string|in:delegation,txt,file_proxy,file',
        ]);

        $userId = $request->attributes->get('authenticated_user_id');

        if (! $userId) {
            $this->error('Unauthorized');
        }

        $product = Product::where('code', $request->input('product_code'))
            ->where('product_type', Product::TYPE_ACME)
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

        // 提交到 ACME 上游
        $domains = array_filter(array_map('trim', explode(',', $request->input('domains'))));
        $validationMethod = $request->input('validation_method');
        $acmeResult = app(OrderService::class)->commitOrder($order->fresh(['latestCert', 'product', 'user']), $domains, $validationMethod);

        if ($acmeResult['code'] !== 1) {
            $this->error($acmeResult['msg']);
        }

        $this->success([
            'order_id' => $order->id,
            'eab_kid' => $result['data']['eab_kid'],
            'eab_hmac' => $result['data']['eab_hmac'],
            'server_url' => $result['data']['server_url'],
            'dcv' => $acmeResult['data']['dcv'] ?? null,
            'validation' => $acmeResult['data']['validation'] ?? null,
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

        $order = AcmeOrder::where('id', $orderId)
            ->where('user_id', $userId)
            ->whereNotNull('eab_kid')
            ->first();

        if (! $order) {
            $this->error('No available EAB credentials for this order.');
        }

        $serverUrl = rtrim(get_system_setting('site', 'url', config('app.url')), '/').'/acme/directory';
        $configDir = "/etc/letsencrypt/$order->eab_kid";
        $configHome = "~/.acme.sh/$order->eab_kid";

        $this->success([
            'eab_kid' => $order->eab_kid,
            'eab_hmac' => $order->eab_hmac,
            'server_url' => $serverUrl,
            'certbot_command' => "certbot certonly --config-dir $configDir --server $serverUrl --eab-kid $order->eab_kid"
                ." --eab-hmac-key $order->eab_hmac"
                .' --preferred-challenges dns-01 --key-type rsa -d example.com',
            'acmesh_register_command' => "acme.sh --register-account --config-home $configHome --server $serverUrl --eab-kid $order->eab_kid"
                ." --eab-hmac-key $order->eab_hmac",
            'acmesh_issue_command' => "acme.sh --issue --config-home $configHome --server $serverUrl --keylength 2048"
                .' --dns --yes-I-know-dns-manual-mode-enough-go-ahead-please -d example.com',
        ]);
    }
}
