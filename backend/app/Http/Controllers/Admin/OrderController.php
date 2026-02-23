<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Order\GetIdsRequest;
use App\Http\Requests\Order\IndexRequest;
use App\Models\Order;
use App\Services\Acme\OrderService as AcmeOrderService;
use App\Services\Order\Action;
use Illuminate\Http\Request;
use Throwable;

class OrderController extends BaseController
{
    protected Action $action;

    public function __construct()
    {
        parent::__construct();

        $this->action = new Action;
    }

    use \App\Http\Traits\OrderController;

    /**
     * 获取订单列表
     *
     * @throws Throwable
     */
    public function index(IndexRequest $request): void
    {
        $validated = $request->validated();
        $currentPage = (int) ($validated['currentPage'] ?? 1);
        $pageSize = (int) ($validated['pageSize'] ?? 10);

        $query = Order::query();

        $statusSet = $validated['statusSet'] ?? 'activating';
        // 活动中的状态
        if ($statusSet === 'activating') {
            $query->whereHas('latestCert', function ($latestCertQuery) {
                $latestCertQuery->whereIn('status', ['unpaid', 'pending', 'processing', 'active', 'approving', 'cancelling']);
            });
        }
        // 已存档的状态
        if ($statusSet === 'archived') {
            $query->whereHas('latestCert', function ($latestCertQuery) {
                $latestCertQuery->whereIn('status', ['cancelled', 'renewed', 'replaced', 'reissued', 'expired', 'revoked', 'failed']);
            });
        }

        if (! empty($validated['quickSearch'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('id', 'like', "%{$validated['quickSearch']}%")
                    ->orWhere('admin_remark', 'like', "%{$validated['quickSearch']}%")
                    ->orWhere('remark', 'like', "%{$validated['quickSearch']}%")
                    ->orWhereHas('user', function ($userQuery) use ($validated) {
                        $userQuery->where('username', 'like', "%{$validated['quickSearch']}%");
                    })
                    ->orWhereHas('product', function ($productQuery) use ($validated) {
                        $productQuery->where('name', 'like', "%{$validated['quickSearch']}%");
                    })
                    ->orWhereHas('latestCert', function ($latestCertQuery) use ($validated) {
                        $latestCertQuery->where('common_name', 'like', "%{$validated['quickSearch']}%")
                            ->orWhere('alternative_names', 'like', "%{$validated['quickSearch']}%");
                    });
            });
        }
        if (! empty($validated['id'])) {
            $query->where('id', $validated['id']);
        }
        if (! empty($validated['period'])) {
            $query->where('period', $validated['period']);
        }
        if (! empty($validated['amount'])) {
            if (isset($validated['amount'][0]) && isset($validated['amount'][1])) {
                $query->whereBetween('amount', $validated['amount']);
            } elseif (isset($validated['amount'][0])) {
                $query->where('amount', '>=', $validated['amount'][0]);
            } elseif (isset($validated['amount'][1])) {
                $query->where('amount', '<=', $validated['amount'][1]);
            }
        }
        if (! empty($validated['created_at'])) {
            $query->whereBetween('created_at', $validated['created_at']);
        }
        if (! empty($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }
        if (! empty($validated['username'])) {
            $query->whereHas('user', function ($userQuery) use ($validated) {
                $userQuery->where('username', $validated['username']);
            });
        }
        if (! empty($validated['product_name'])) {
            $query->whereHas('product', function ($productQuery) use ($validated) {
                $productQuery->where('name', 'like', "%{$validated['product_name']}%");
            });
        }
        if (! empty($validated['domain'])) {
            $query->whereHas('latestCert', function ($latestCertQuery) use ($validated) {
                $latestCertQuery->where(function ($q) use ($validated) {
                    $q->where('common_name', 'like', "%{$validated['domain']}%")
                        ->orWhere('alternative_names', 'like', "%{$validated['domain']}%");
                });
            });
        }
        if (! empty($validated['channel'])) {
            $query->whereHas('latestCert', function ($latestCertQuery) use ($validated) {
                $latestCertQuery->where('channel', $validated['channel']);
            });
        }
        if (! empty($validated['action'])) {
            $query->whereHas('latestCert', function ($latestCertQuery) use ($validated) {
                $latestCertQuery->where('action', $validated['action']);
            });
        }
        if (! empty($validated['expires_at'])) {
            $query->whereHas('latestCert', function ($latestCertQuery) use ($validated) {
                $latestCertQuery->whereBetween('expires_at', $validated['expires_at']);
            });
        }
        if (! empty($validated['status'])) {
            $query->whereHas('latestCert', function ($latestCertQuery) use ($validated) {
                $latestCertQuery->where('status', $validated['status']);
            });
        }

        $total = $query->count();
        $items = $query->with([
            'user' => function ($query) {
                $query->select(['id', 'username']);
            }, 'product' => function ($query) {
                $query->select(['id', 'name', 'product_type', 'refund_period']);
            }, 'latestCert' => function ($query) {
                $query->select(['id', 'common_name', 'channel', 'action', 'dcv', 'validation', 'status', 'amount', 'issuer']);
            },
        ])
            ->select(['id', 'user_id', 'product_id', 'latest_cert_id', 'period', 'amount', 'created_at'])
            ->orderBy('latest_cert_id', 'desc')
            ->offset(($currentPage - 1) * $pageSize)
            ->limit($pageSize)
            ->get();

        // issuer 在 cert 模型中用于判断中级证书是否存在 查询后可以去除
        // 去除 issuer 和 intermediate_cert 字段
        $items->each(function ($item) {
            $item->latestCert->makeHidden(['issuer', 'intermediate_cert']);
        });

        $this->success([
            'items' => $items,
            'total' => $total,
            'pageSize' => $pageSize,
            'currentPage' => $currentPage,
        ]);
    }

    /**
     * 获取订单资料
     */
    public function show($id): void
    {
        $order = Order::with([
            'user' => function ($query) {
                $query->select(['id', 'username', 'email', 'mobile']);
            }, 'product' => function ($query) {
                $query->select(['id', 'name', 'product_type', 'ca', 'refund_period', 'validation_methods', 'validation_type', 'common_name_types', 'alternative_name_types', 'support_acme']);
            }, 'latestCert',
        ])->find($id);

        if (! $order) {
            $this->error('订单不存在');
        }

        $data = $order->toArray();

        // ACME 订单附加信息
        if ($order->latestCert && $order->latestCert->channel === 'acme') {
            // 懒补充 dcv/validation 数据（兼容旧数据）
            if (empty($order->latestCert->dcv)) {
                app(AcmeOrderService::class)->populateDcvAndValidation($order->latestCert);
                $order->latestCert->refresh();
            }

            $order->load('latestCert.acmeAuthorizations');
            $data['latest_cert'] = $order->latestCert->toArray();
            $data['is_acme'] = true;
            $data['eab_kid'] = $order->eab_kid;
            $data['eab_hmac'] = $order->eab_hmac;
            $data['eab_used'] = $order->eab_used_at !== null;
            $serverUrl = rtrim(get_system_setting('site', 'url', config('app.url')), '/').'/acme/directory';
            $data['server_url'] = $serverUrl;
            $data['certbot_command'] = "certbot certonly --server $serverUrl --eab-kid $order->eab_kid"
                ." --eab-hmac-key $order->eab_hmac"
                .' -d example.com --preferred-challenges dns-01';
            $data['acmesh_command'] = "acme.sh --register-account --server $serverUrl --eab-kid $order->eab_kid"
                ." --eab-hmac-key $order->eab_hmac";
        }

        $this->success($data);
    }

    /**
     * 批量获取订单资料
     */
    public function batchShow(GetIdsRequest $request): void
    {
        $ids = $request->validated('ids');

        $orders = Order::whereIn('id', $ids)
            ->with([
                'user' => function ($query) {
                    $query->select(['id', 'username', 'email', 'mobile']);
                }, 'product' => function ($query) {
                    $query->select(['id', 'name', 'product_type', 'ca', 'refund_period', 'validation_methods', 'validation_type', 'common_name_types', 'alternative_name_types', 'support_acme']);
                }, 'latestCert',
            ])
            ->get();
        if ($orders->isEmpty()) {
            $this->error('订单不存在');
        }

        $result = $orders->map(function ($order) {
            $data = $order->toArray();

            if ($order->latestCert && $order->latestCert->channel === 'acme') {
                $data['is_acme'] = true;
                $data['eab_kid'] = $order->eab_kid;
                $data['eab_hmac'] = $order->eab_hmac;
                $data['eab_used'] = $order->eab_used_at !== null;
                $serverUrl = rtrim(get_system_setting('site', 'url', config('app.url')), '/').'/acme/directory';
                $data['server_url'] = $serverUrl;
                $data['certbot_command'] = "certbot certonly --server $serverUrl --eab-kid $order->eab_kid"
                    ." --eab-hmac-key $order->eab_hmac"
                    .' -d example.com --preferred-challenges dns-01';
                $data['acmesh_command'] = "acme.sh --register-account --server $serverUrl --eab-kid $order->eab_kid"
                    ." --eab-hmac-key $order->eab_hmac";
            }

            return $data;
        });

        $this->success($result->toArray());
    }

    /**
     * 申请
     *
     * @throws Throwable
     */
    public function new(): void
    {
        $params = request()->post();
        $params['action'] = 'new';
        $params['channel'] = 'admin';
        $this->action->new($params);
    }

    /**
     * 批量申请
     *
     * @throws Throwable
     */
    public function batchNew(): void
    {
        $params = request()->post();
        $params['action'] = 'new';
        $params['channel'] = 'admin';
        $params['is_batch'] = true;
        $this->action->batchNew($params);
    }

    /**
     * 续费
     *
     * @throws Throwable
     */
    public function renew(): void
    {
        $params = request()->post();
        $params['action'] = 'renew';
        $params['channel'] = 'admin';
        $this->action->renew($params);
    }

    /**
     * 重签
     *
     * @throws Throwable
     */
    public function reissue(): void
    {
        $params = request()->post();
        $params['action'] = 'reissue';
        $params['channel'] = 'admin';
        $this->action->reissue($params);
    }

    /**
     * 转移
     *
     * @throws Throwable
     */
    public function transfer(): void
    {
        $params = request()->post();
        $this->action->transfer($params);
    }

    /**
     * 修改未支付订单价格
     */
    public function updateAmount(Request $request, int $id): void
    {
        $amount = $request->validate([
            'amount' => ['required', 'numeric', 'min:0', 'max:999998', 'regex:/^\d+(\.\d{1,2})?$/'],
        ])['amount'];

        $order = Order::with('latestCert')->find($id);
        if (! $order) {
            $this->error('订单不存在');
        }

        $cert = $order->latestCert;
        if (! $cert || $cert->status !== 'unpaid') {
            $this->error('只有未支付状态的订单可以修改价格');
        }

        $cert->amount = $amount;
        $cert->save();

        $this->success();
    }

    /**
     * 导入证书
     *
     * @throws Throwable
     */
    public function input(): void
    {
        $params = request()->post();
        $this->action->input($params);
    }
}
