<?php

namespace App\Http\Controllers\User;

use App\Models\Acme;
use App\Services\Acme\Action;
use Illuminate\Http\Request;

class AcmeController extends BaseController
{
    protected Action $action;

    public function __construct()
    {
        parent::__construct();
        $this->action = app(Action::class);
    }

    /**
     * ACME 订单列表（UserScope 自动过滤当前用户）
     */
    public function index(Request $request): void
    {
        $currentPage = (int) ($request->input('currentPage', 1));
        $pageSize = (int) ($request->input('pageSize', 10));

        $query = Acme::query();

        if ($request->filled('brand')) {
            $query->where('brand', $request->input('brand'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $total = $query->count();
        $items = $query->with(['product'])
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
     * 订单详情（UserScope 自动过滤当前用户）
     */
    public function show(int $id): void
    {
        $order = Acme::with(['product'])->find($id);

        if (! $order) {
            $this->error('订单不存在');
        }

        $this->success($order->makeVisible('eab_hmac')->toArray());
    }

    /**
     * 创建 ACME 订单
     */
    public function new(Request $request): void
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'period' => 'required|integer',
            'purchased_standard_count' => 'integer|min:0',
            'purchased_wildcard_count' => 'integer|min:0',
        ]);

        $this->action->new([
            'user_id' => $this->guard->id(),
            'product_id' => $request->input('product_id'),
            'period' => $request->input('period'),
            'purchased_standard_count' => (int) $request->input('purchased_standard_count', 0),
            'purchased_wildcard_count' => (int) $request->input('purchased_wildcard_count', 0),
        ]);
    }

    /**
     * 支付订单
     */
    public function pay(int $id): void
    {
        $this->action->pay($id);
    }

    /**
     * 提交订单到 Gateway
     */
    public function commit(int $id): void
    {
        $this->action->commit($id);
    }

    /**
     * 取消订单
     */
    public function commitCancel(int $id): void
    {
        $this->action->commitCancel($id);
    }
}
