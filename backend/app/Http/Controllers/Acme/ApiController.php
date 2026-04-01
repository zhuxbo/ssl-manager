<?php

namespace App\Http\Controllers\Acme;

use App\Http\Controllers\Controller;
use App\Models\Acme;
use App\Models\ApiToken;
use App\Models\Product;
use App\Services\Acme\Action;
use App\Services\Order\Utils\OrderUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiController extends Controller
{
    protected int $user_id;

    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;

        /** @var ApiToken $apiToken */
        $apiToken = Auth::guard('api')->user();
        $this->user_id = $apiToken->user_id;
    }

    /**
     * 创建 ACME 订单（一步到位：创建 + 支付 + 提交）
     */
    public function new(): void
    {
        $this->request->validate([
            'product_code' => 'required|string|max:50',
            'period' => 'sometimes|integer',
            'purchased_standard_count' => 'sometimes|integer|min:0',
            'purchased_wildcard_count' => 'sometimes|integer|min:0',
        ]);

        $product = Product::where('code', $this->request->input('product_code'))
            ->where('product_type', Product::TYPE_ACME)
            ->where('status', 1)
            ->first();

        if (! $product) {
            $this->error('Product not found');
        }

        app(Action::class)->newAndCommit([
            'user_id' => $this->user_id,
            'product_id' => $product->id,
            'period' => (int) $this->request->input('period', $product->periods[0] ?? 12),
            'purchased_standard_count' => (int) $this->request->input('purchased_standard_count', 0),
            'purchased_wildcard_count' => (int) $this->request->input('purchased_wildcard_count', 0),
        ]);
    }

    /**
     * 获取订单详情（含 EAB）
     */
    public function get(): void
    {
        $this->request->validate(['order_id' => 'required|integer|min:1']);

        $id = (int) $this->request->input('order_id');

        // 先同步上游最新状态
        app(Action::class)->sync($id, true);

        $acme = Acme::find($id);

        if (! $acme) {
            $this->error('Order not found');
        }

        $this->success($acme->makeVisible('eab_hmac')->toArray());
    }

    /**
     * 取消订单
     */
    public function cancel(): void
    {
        $this->request->validate(['order_id' => 'required|integer|min:1']);

        app(Action::class)->commitCancel((int) $this->request->input('order_id'));
    }

    /**
     * 获取 ACME 产品列表
     */
    public function getProducts(): void
    {
        $brand = $this->request->input('brand', '');
        $code = $this->request->input('code', '');

        $where = [];
        $brand && $where[] = ['brand', '=', $brand];
        $code && $where[] = ['code', 'like', '%'.$code.'%'];
        $where[] = ['status', '=', 1];
        $where[] = ['product_type', '=', Product::TYPE_ACME];

        $res = Product::where($where)->orderBy('weight', 'ASC')->get();
        $res->makeHidden(['id', 'api_id', 'cost', 'status', 'created_at', 'updated_at']);

        $data = [];
        foreach ($res as $item) {
            $cost = [];
            $skipProduct = false;

            /** @var int $period */
            foreach ($item->periods as $period) {
                $minPrice = OrderUtil::getMinPrice($this->user_id, $item->id, (int) $period);

                if (empty($minPrice)) {
                    $skipProduct = true;
                    break;
                }

                $period = (string) $period;
                $cost['price'][$period] = $minPrice['price'];

                if (in_array('standard', $item->alternative_name_types)) {
                    $cost['alternative_standard_price'][$period] = $minPrice['alternative_standard_price'];
                }

                if (in_array('wildcard', $item->alternative_name_types)) {
                    $cost['alternative_wildcard_price'][$period] = $minPrice['alternative_wildcard_price'];
                }
            }

            if ($skipProduct) {
                continue;
            }

            $item = $item->toArray();
            $item['periods'] = array_map('intval', $item['periods']);
            $item['cost'] = $cost;
            $data[] = $item;
        }

        $this->success($data);
    }
}
