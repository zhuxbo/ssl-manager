<?php

namespace App\Http\Controllers\Admin;

use App\Models\Acme\AcmeCert;
use App\Models\Acme\AcmeOrder;
use App\Models\User;
use App\Services\Acme\BillingService;
use App\Services\Acme\OrderService;
use Illuminate\Http\Request;

class AcmeController extends BaseController
{
    /**
     * ACME 订单列表
     */
    public function index(Request $request): void
    {
        $currentPage = (int) ($request->input('currentPage', 1));
        $pageSize = (int) ($request->input('pageSize', 10));

        $query = AcmeOrder::query();

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }
        if ($request->filled('brand')) {
            $query->where('brand', $request->input('brand'));
        }
        if ($request->filled('status')) {
            $query->whereHas('latestCert', function ($q) use ($request) {
                $q->where('status', $request->input('status'));
            });
        }

        $total = $query->count();
        $items = $query->with(['latestCert', 'user', 'product'])
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
     * 订单详情
     */
    public function show(int $id): void
    {
        $order = AcmeOrder::with(['latestCert.acmeAuthorizations', 'user', 'product'])->find($id);

        if (! $order) {
            $this->error('订单不存在');
        }

        $this->success($order->toArray());
    }

    /**
     * 同步订单状态
     */
    public function syncOrder(int $id): void
    {
        $order = AcmeOrder::with('latestCert')->findOrFail($id);
        app(OrderService::class)->syncOrder($order->latestCert);
        $this->success();
    }

    /**
     * 重新验证域名
     */
    public function revalidate(int $id): void
    {
        $order = AcmeOrder::with('latestCert')->findOrFail($id);
        app(OrderService::class)->acmeRevalidate($order->latestCert);
        $this->success();
    }

    /**
     * 切换验证方式
     */
    public function updateDCV(Request $request, int $id): void
    {
        $order = AcmeOrder::with('latestCert')->findOrFail($id);
        app(OrderService::class)->acmeUpdateDCV($order->latestCert, $request->input('method'));
        $this->success();
    }

    /**
     * 取消订单
     */
    public function commitCancel(int $id): void
    {
        $order = AcmeOrder::with('latestCert')->findOrFail($id);
        $result = app(BillingService::class)->executeCancel($order);

        if ($result['code'] === 1) {
            $this->success();
        } else {
            $this->error($result['msg']);
        }
    }

    /**
     * 吊销证书
     */
    public function commitRevoke(Request $request, int $id): void
    {
        $order = AcmeOrder::with('latestCert')->findOrFail($id);
        $cert = $order->latestCert;

        if (empty($cert->serial_number)) {
            $this->error('证书序列号未知');
        }

        $result = app(OrderService::class)->revokeCertificateUpstream($cert);

        if ($result['code'] === 1) {
            $this->success();
        } else {
            $this->error($result['msg']);
        }
    }

    /**
     * 删除未提交订单
     */
    public function destroy(int $id): void
    {
        $order = AcmeOrder::with('latestCert.acmeAuthorizations')->findOrFail($id);
        $cert = $order->latestCert;

        if (! empty($cert->api_id)) {
            $this->error('已提交上游的订单不允许直接删除');
        }

        $cert->acmeAuthorizations()->delete();
        $cert->delete();
        $order->delete();

        $this->success();
    }

    /**
     * 备注
     */
    public function remark(Request $request, int $id): void
    {
        $order = AcmeOrder::findOrFail($id);
        $order->update(['remark' => $request->string('remark')->trim()->limit(255)]);
        $this->success();
    }

    /**
     * 创建 ACME 订阅订单（unpaid 状态，用户从详情页支付+提交）
     */
    public function createOrder(Request $request): void
    {
        $request->validate([
            'user_id' => 'required|integer',
            'product_id' => 'required|integer',
            'period' => 'required|integer',
            'domains' => 'required|string|max:5000',
            'validation_method' => 'required|string|in:delegation,txt,file_proxy,file',
        ]);

        $user = User::findOrFail($request->input('user_id'));
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
     * 获取订单 EAB 信息
     */
    public function getEab(Request $request, int $orderId): void
    {
        $order = AcmeOrder::where('id', $orderId)
            ->whereNotNull('eab_kid')
            ->firstOrFail();

        $serverUrl = rtrim(get_system_setting('site', 'url', config('app.url')), '/').'/acme/directory';
        $configDir = "/etc/letsencrypt/$order->eab_kid";
        $configHome = "~/.acme.sh/$order->eab_kid";

        $this->success([
            'order_id' => $order->id,
            'eab_kid' => $order->eab_kid,
            'eab_hmac' => $order->eab_hmac,
            'eab_used' => $order->eab_used_at !== null,
            'server_url' => $serverUrl,
            'certbot_command' => "certbot certonly --config-dir $configDir --server $serverUrl --eab-kid $order->eab_kid"
                ." --eab-hmac-key $order->eab_hmac"
                .' -d example.com --preferred-challenges dns-01',
            'acmesh_command' => "acme.sh --register-account --config-home $configHome --server $serverUrl --eab-kid $order->eab_kid"
                ." --eab-hmac-key $order->eab_hmac",
        ]);
    }

    /**
     * ACME 证书列表
     */
    public function certIndex(Request $request): void
    {
        $currentPage = (int) ($request->input('currentPage', 1));
        $pageSize = (int) ($request->input('pageSize', 10));

        $query = AcmeCert::query();

        if ($request->filled('domain')) {
            $query->where('common_name', 'like', "%{$request->input('domain')}%");
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('order_id')) {
            $query->where('order_id', $request->input('order_id'));
        }

        $total = $query->count();
        $items = $query->orderByDesc('id')
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
     * ACME 证书详情
     */
    public function certShow(int $id): void
    {
        $cert = AcmeCert::with('acmeAuthorizations')->findOrFail($id);
        $this->success($cert->toArray());
    }
}
