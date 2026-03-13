<?php

namespace App\Http\Controllers\Admin;

use App\Models\Acme;
use App\Models\User;
use App\Services\Acme\Action;
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
     * 创建 ACME 订单（unpaid 状态）
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

        $user = User::findOrFail($request->input('user_id'));

        $acme = app(Action::class)->new(
            $user,
            $request->input('product_id'),
            $request->input('period'),
            (int) $request->input('purchased_standard_count', 0),
            (int) $request->input('purchased_wildcard_count', 0),
        );

        $this->success(['order_id' => $acme->id]);
    }

    /**
     * 支付订单
     */
    public function pay(int $id): void
    {
        $acme = Acme::findOrFail($id);
        app(Action::class)->pay($acme);
        $this->success();
    }

    /**
     * 提交订单到 Gateway
     */
    public function commit(int $id): void
    {
        $acme = Acme::findOrFail($id);
        $acme = app(Action::class)->commit($acme);

        $acme->makeVisible('eab_hmac');

        $this->success([
            'order_id' => $acme->id,
            'eab_kid' => $acme->eab_kid,
            'eab_hmac' => $acme->eab_hmac,
        ]);
    }

    /**
     * 同步订单状态
     */
    public function sync(int $id): void
    {
        app(Action::class)->sync($id);
    }

    /**
     * 取消订单
     */
    public function commitCancel(int $id): void
    {
        $acme = Acme::findOrFail($id);
        app(Action::class)->commitCancel($acme);
        $this->success();
    }

    /**
     * 备注
     */
    public function remark(int $id, Request $request): void
    {
        $acme = Acme::findOrFail($id);
        $acme->update(['admin_remark' => $request->string('remark')->trim()->limit(255)]);
        $this->success();
    }
}
