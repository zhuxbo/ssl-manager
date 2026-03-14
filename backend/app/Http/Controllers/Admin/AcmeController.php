<?php

namespace App\Http\Controllers\Admin;

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
     * ACME 订单列表
     */
    public function index(Request $request): void
    {
        $currentPage = (int) ($request->input('currentPage', 1));
        $pageSize = (int) ($request->input('pageSize', 10));

        $query = Acme::query();

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }
        if ($request->filled('brand')) {
            $query->where('brand', $request->input('brand'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $total = $query->count();
        $items = $query->with(['user', 'product'])
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
     * 订单详情（含 EAB，makeVisible eab_hmac）
     */
    public function show(int $id): void
    {
        $order = Acme::with(['user', 'product'])->find($id);

        if (! $order) {
            $this->error('订单不存在');
        }

        $order->makeVisible('eab_hmac');

        $this->success($order->toArray());
    }

    /**
     * 创建 ACME 订单
     */
    public function new(Request $request): void
    {
        $request->validate([
            'user_id' => 'required|integer',
            'product_id' => 'required|integer|exists:products,id',
            'period' => 'required|integer',
            'purchased_standard_count' => 'integer|min:0',
            'purchased_wildcard_count' => 'integer|min:0',
        ]);

        $this->action->new($request->only([
            'user_id', 'product_id', 'period',
            'purchased_standard_count', 'purchased_wildcard_count', 'remark',
        ]));
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
     * 同步订单状态
     */
    public function sync(int $id): void
    {
        $this->action->sync($id);
    }

    /**
     * 取消订单
     */
    public function commitCancel(int $id): void
    {
        $this->action->commitCancel($id);
    }

    /**
     * 备注
     */
    public function remark(int $id, Request $request): void
    {
        $this->action->remark($id, $request->string('remark')->trim()->limit(255), 'admin_remark');
    }
}
