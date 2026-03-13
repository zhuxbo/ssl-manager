<?php

namespace App\Http\Controllers\Deploy;

use App\Http\Controllers\Controller;
use App\Models\Acme;
use App\Models\Product;
use App\Models\User;
use App\Services\Acme\Action;
use Illuminate\Http\Request;

class AcmeController extends Controller
{
    /**
     * 创建 ACME 订单（一步到位：创建 + 支付 + 提交）
     */
    public function new(Request $request): void
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'period' => 'required|integer',
            'purchased_standard_count' => 'integer|min:0',
            'purchased_wildcard_count' => 'integer|min:0',
        ]);

        $userId = $request->attributes->get('authenticated_user_id');

        if (! $userId) {
            $this->error('Unauthorized');
        }

        $product = Product::where('id', $request->input('product_id'))
            ->where('product_type', Product::TYPE_ACME)
            ->first();

        if (! $product) {
            $this->error('Product not found or does not support ACME');
        }

        $user = User::findOrFail($userId);
        $action = new Action($userId);

        // 创建订单
        $acme = $action->new(
            $user,
            $product->id,
            $request->input('period'),
            (int) $request->input('purchased_standard_count', 0),
            (int) $request->input('purchased_wildcard_count', 0),
        );

        // 支付
        $acme = $action->pay($acme);

        // 提交到 Gateway
        $acme = $action->commit($acme);

        $acme->makeVisible('eab_hmac');

        $this->success([
            'order_id' => $acme->id,
            'eab_kid' => $acme->eab_kid,
            'eab_hmac' => $acme->eab_hmac,
            'status' => $acme->status,
        ]);
    }

    /**
     * 查询订单详情（含 EAB）
     */
    public function get(int $id): void
    {
        $userId = request()->attributes->get('authenticated_user_id');

        if (! $userId) {
            $this->error('Unauthorized');
        }

        $acme = Acme::where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (! $acme) {
            $this->error('Order not found');
        }

        $acme->makeVisible('eab_hmac');

        $this->success($acme->toArray());
    }
}
