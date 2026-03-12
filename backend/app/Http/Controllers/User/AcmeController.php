<?php

namespace App\Http\Controllers\User;

use App\Models\Acme\AcmeOrder;
use App\Services\Acme\BillingService;
use Illuminate\Http\Request;

class AcmeController extends BaseController
{
    /**
     * ACME 订单列表（限当前用户）
     */
    public function index(Request $request): void
    {
        $currentPage = (int) ($request->input('currentPage', 1));
        $pageSize = (int) ($request->input('pageSize', 10));

        $query = AcmeOrder::where('user_id', $this->guard->id());

        if ($request->filled('brand')) {
            $query->where('brand', $request->input('brand'));
        }
        if ($request->filled('status')) {
            $query->whereHas('latestCert', function ($q) use ($request) {
                $q->where('status', $request->input('status'));
            });
        }

        $total = $query->count();
        $items = $query->with(['latestCert', 'product'])
            ->orderByDesc('id')
            ->offset(($currentPage - 1) * $pageSize)
            ->limit($pageSize)
            ->get();

        $this->success([
            'items' => $items,
            'total' => $total,
            'pageSize' => $pageSize,
            'currentPage' => $currentPage,
        ]);
    }

    /**
     * 订单详情（限当前用户）
     */
    public function show(int $id): void
    {
        $order = AcmeOrder::with(['latestCert.acmeAuthorizations', 'product'])
            ->where('user_id', $this->guard->id())
            ->find($id);

        if (! $order) {
            $this->error('订单不存在');
        }

        $this->success($order->toArray());
    }

    /**
     * 取消订单（限当前用户）
     */
    public function commitCancel(int $id): void
    {
        $order = AcmeOrder::with('latestCert')
            ->where('user_id', $this->guard->id())
            ->findOrFail($id);

        $result = app(BillingService::class)->executeCancel($order);

        if ($result['code'] === 1) {
            $this->success();
        } else {
            $this->error($result['msg']);
        }
    }

    /**
     * 创建 ACME 订阅订单（unpaid 状态，用户从详情页支付+提交）
     */
    public function createOrder(Request $request): void
    {
        $request->validate([
            'product_id' => 'required|integer',
            'period' => 'required|integer',
            'domains' => 'required|string|max:5000',
            'validation_method' => 'required|string|in:delegation,txt,file_proxy,file',
        ]);

        $user = $this->guard->user();
        $domains = array_filter(array_map('trim', explode(',', $request->input('domains'))));

        $result = app(BillingService::class)->createSubscription(
            $user,
            $request->input('product_id'),
            $request->input('period'),
            $domains,
            $request->input('validation_method'),
        );

        if ($result['code'] !== 1) {
            $this->error($result['msg']);
        }

        $this->success([
            'order_id' => $result['data']['order']->id,
            'eab_kid' => $result['data']['eab_kid'],
            'eab_hmac' => $result['data']['eab_hmac'],
        ]);
    }

    /**
     * 查询 EAB 状态（按 orderId 精确查询）
     */
    public function getEab(Request $request, int $orderId): void
    {
        $order = AcmeOrder::where('id', $orderId)
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
