<?php

namespace App\Http\Controllers\Deploy;

use App\Http\Controllers\Controller;
use App\Models\Acme;
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

        app(Action::class)->deployNew([
            'user_id' => $userId,
            'product_id' => $request->input('product_id'),
            'period' => $request->input('period'),
            'purchased_standard_count' => (int) $request->input('purchased_standard_count', 0),
            'purchased_wildcard_count' => (int) $request->input('purchased_wildcard_count', 0),
        ]);
    }

    /**
     * 查询订单详情（含 EAB）
     *
     * deploy_tokens.user_id 为 non-nullable，DB 约束保证非空，
     * DeployAuthenticate 始终注册 UserScope，Acme::find 自动过滤当前用户
     */
    public function get(int $id): void
    {
        $acme = Acme::find($id);

        if (! $acme) {
            $this->error('Order not found');
        }

        $acme->makeVisible('eab_hmac');

        $this->success($acme->toArray());
    }
}
