<?php

namespace App\Http\Controllers\User;

use App\Models\Acme;
use App\Services\Acme\Action;
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

        $query = Acme::where('user_id', $this->guard->id());

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
     * 订单详情（限当前用户）
     */
    public function show(int $id): void
    {
        $order = Acme::with(['product'])
            ->where('user_id', $this->guard->id())
            ->find($id);

        if (! $order) {
            $this->error('订单不存在');
        }

        $this->success($order->makeVisible('eab_hmac')->toArray());
    }

    /**
     * 创建 ACME 订单（unpaid 状态）
     */
    public function new(Request $request): void
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'period' => 'required|integer',
            'purchased_standard_count' => 'integer|min:0',
            'purchased_wildcard_count' => 'integer|min:0',
        ]);

        $user = $this->guard->user();

        $acme = (new Action($user->id))->new(
            $user,
            $request->input('product_id'),
            $request->input('period'),
            (int) $request->input('purchased_standard_count', 0),
            (int) $request->input('purchased_wildcard_count', 0),
        );

        $this->success(['order_id' => $acme->id]);
    }

    /**
     * 支付订单（限当前用户）
     */
    public function pay(int $id): void
    {
        $acme = Acme::where('user_id', $this->guard->id())->findOrFail($id);
        (new Action($this->guard->id()))->pay($acme);
        $this->success();
    }

    /**
     * 提交订单到 Gateway（限当前用户）
     */
    public function commit(int $id): void
    {
        $acme = Acme::where('user_id', $this->guard->id())->findOrFail($id);
        $acme = (new Action($this->guard->id()))->commit($acme);

        $acme->makeVisible('eab_hmac');

        $this->success([
            'order_id' => $acme->id,
            'eab_kid' => $acme->eab_kid,
            'eab_hmac' => $acme->eab_hmac,
        ]);
    }

    /**
     * 取消订单（限当前用户）
     */
    public function commitCancel(int $id): void
    {
        $acme = Acme::where('user_id', $this->guard->id())->findOrFail($id);
        (new Action($this->guard->id()))->commitCancel($acme);
        $this->success();
    }
}
