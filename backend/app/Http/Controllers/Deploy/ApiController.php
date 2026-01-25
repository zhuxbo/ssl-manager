<?php

namespace App\Http\Controllers\Deploy;

use App\Exceptions\ApiResponseException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Order\Action;
use Illuminate\Http\Request;
use Throwable;

class ApiController extends Controller
{
    /**
     * 按域名查询订单列表
     * 支持精确匹配和通配符匹配
     */
    public function query(Request $request): void
    {
        $request->validate([
            'domain' => ['required', 'string'],
        ]);

        $domain = strtolower(trim($request->input('domain')));

        // 查询匹配的订单
        $orders = $this->findOrdersByDomain($domain);

        if ($orders->isEmpty()) {
            $this->error('未找到匹配的订单');
        }

        $data = $orders->map(fn ($order) => $this->getOrderData($order))->values()->toArray();

        $this->success($data);
    }

    /**
     * 更新/续费证书
     *
     * @throws Throwable
     */
    public function update(Request $request): void
    {
        $params = $request->validate([
            'order_id' => ['nullable', 'integer'],
            'domain' => ['nullable', 'string'],
            'csr' => ['nullable', 'string'],
            'domains' => ['nullable', 'string'], // 多域名支持，逗号分割
            'validation_method' => ['nullable', 'in:txt,file,http,https,cname,admin,administrator,postmaster,webmaster,hostmaster'],
        ]);

        // 通过 order_id 或 domain 查找订单
        $order = $this->findOrder($params);

        if (! $order || ! $order->latestCert) {
            $this->error('订单不存在');
        }

        $cert = $order->latestCert;
        $orderId = $order->id;
        $reQuery = false;

        // 证书状态如果是 unpaid 则支付并提交
        // 如果参数有 csr 则替换 csr 并删除 private_key
        if ($cert->status === 'unpaid') {
            if (! empty($params['csr'])) {
                $cert->csr = $params['csr'];
                $cert->private_key = null;
                $cert->save();
            }

            $action = new Action;
            $action->pay($orderId);
            $reQuery = true;
        }

        // 证书状态如果是 pending 则提交
        if ($cert->status === 'pending') {
            $action = new Action;
            $action->commit($orderId);
            $reQuery = true;
        }

        // 证书状态如果是 active 则发起重签或续费
        if ($cert->status === 'active') {
            $updateParams = [
                'order_id' => $orderId,
            ];

            if (empty($params['csr'])) {
                $updateParams['csr_generate'] = 1;
            } else {
                $updateParams['csr_generate'] = 0;
                $updateParams['csr'] = $params['csr'];
            }

            $updateParams['channel'] = 'api';
            // 优先使用客户端传入的 domains，否则使用当前证书的域名
            $updateParams['domains'] = ! empty($params['domains'])
                ? trim($params['domains'])
                : $cert->alternative_names;
            $updateParams['validation_method'] = $params['validation_method'] ?? 'txt';

            // 如果订单到期时间小于 15 天则续费，否则重签
            $action = new Action;
            if ($order->period_till?->lt(now()->addDays(15))) {
                try {
                    $updateParams['action'] = 'renew';
                    $action->renew($updateParams);
                } catch (ApiResponseException $e) {
                    $result = $e->getApiResponse();

                    if ($result['code'] === 0) {
                        $this->error($result['msg'], $result['errors'] ?? null);
                    }

                    $orderId = $result['data']['order_id'];
                }
            } else {
                $updateParams['action'] = 'reissue';
                $action->reissue($updateParams);
            }

            $reQuery = true;
        }

        if ($reQuery) {
            $order = Order::with('latestCert')->whereHas('latestCert')->where('id', $orderId)->first();

            if (! $order) {
                $this->error('订单不存在');
            }
        }

        $data = $this->getOrderData($order);

        $this->success($data);
    }

    /**
     * 部署回调接口
     * 部署工具完成部署后调用此接口通知 Manager
     */
    public function callback(Request $request): void
    {
        $params = $request->validate([
            'order_id' => ['required', 'integer'],
            'domain' => ['required', 'string'],
            'status' => ['required', 'in:success,failure'],
            'deployed_at' => ['nullable', 'string'],
            'cert_expires_at' => ['nullable', 'string'],
            'cert_serial' => ['nullable', 'string'],
            'server_type' => ['nullable', 'string'],
            'message' => ['nullable', 'string'],
        ]);

        // 通过 Order 查询（Order 已被 UserScope 限制）
        $order = Order::with('latestCert')
            ->where('id', $params['order_id'])
            ->first();

        if (! $order || ! $order->latestCert) {
            $this->error('订单不存在');
        }

        $cert = $order->latestCert;

        // 只有部署成功才记录时间
        if ($params['status'] === 'success') {
            $deployTime = now();

            // 如果传了 deployed_at，尝试解析
            if (! empty($params['deployed_at'])) {
                try {
                    $deployTime = \Carbon\Carbon::parse($params['deployed_at']);
                } catch (\Exception) {
                    // 解析失败使用当前时间
                }
            }

            $cert->auto_deploy_at = $deployTime;
            $cert->save();
        }

        $this->success([
            'order_id' => $params['order_id'],
            'domain' => $params['domain'],
            'status' => $params['status'],
            'recorded' => $params['status'] === 'success',
        ]);
    }

    /**
     * 通过 order_id 或 domain 查找订单
     */
    private function findOrder(array $params): Order
    {
        // 优先使用 order_id
        if (! empty($params['order_id'])) {
            // 通过 Order 查询（Order 已被 UserScope 限制）
            $order = Order::with('latestCert')
                ->whereHas('latestCert')
                ->where('id', $params['order_id'])
                ->first();

            if (! $order || ! $order->latestCert) {
                $this->error('订单不存在');
            }

            return $order;
        }

        // 使用 domain 查找
        if (! empty($params['domain'])) {
            $domain = strtolower(trim($params['domain']));
            $orders = $this->findOrdersByDomain($domain);

            if ($orders->isEmpty()) {
                $this->error('未找到匹配的订单');
            }

            // 返回第一个匹配的订单
            return $orders->first();
        }

        $this->error('请提供 order_id 或 domain 参数');
    }

    /**
     * 按域名查找订单
     * 支持精确匹配和通配符匹配（如 api.example.com 匹配 *.example.com）
     * Order 已被 UserScope 限制
     */
    private function findOrdersByDomain(string $domain): \Illuminate\Database\Eloquent\Collection
    {
        // 提取基础域名（去掉第一级子域名用于通配符匹配）
        $parts = explode('.', $domain);
        $wildcardDomain = count($parts) > 2 ? '*.' . implode('.', array_slice($parts, 1)) : null;

        // 通过 Order 查询（Order 已被 UserScope 限制）
        return Order::with('latestCert')
            ->whereHas('latestCert', function ($query) use ($domain, $wildcardDomain) {
                $query->where(function ($q) use ($domain, $wildcardDomain) {
                    // 精确匹配 common_name
                    $q->where('common_name', $domain);

                    // 精确匹配 alternative_names（JSON 包含）
                    $q->orWhereJsonContains('alternative_names', $domain);

                    // 通配符匹配
                    if ($wildcardDomain) {
                        $q->orWhere('common_name', $wildcardDomain);
                        $q->orWhereJsonContains('alternative_names', $wildcardDomain);
                    }
                })
                    ->whereIn('status', ['active', 'processing', 'pending', 'unpaid']);
            })
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * 统一返回数据
     */
    private function getOrderData(Order $order): array
    {
        $cert = $order->latestCert;

        $data = [
            'order_id' => $order->id,
            'domain' => $cert->common_name,
            'domains' => $cert->alternative_names,
            'status' => $cert->status,
            'certificate' => $cert->cert,
            'private_key' => $cert->private_key,
            'ca_certificate' => $cert->intermediate_cert,
            'expires_at' => $cert->expires_at?->toDateString(),
            'created_at' => $cert->created_at?->toDateString(),
        ];

        // 空值守卫：dcv 可能为 null
        $dcvMethod = $cert->dcv['method'] ?? null;
        if (in_array($dcvMethod, ['file', 'http', 'https']) && $cert->status === 'processing') {
            $data['file'] = [
                'path' => $cert->dcv['file']['path'] ?? '',
                'content' => $cert->dcv['file']['content'] ?? '',
            ];
        }

        return $data;
    }
}
