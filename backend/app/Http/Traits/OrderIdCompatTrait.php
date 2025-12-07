<?php

namespace App\Http\Traits;

use App\Models\Order;
use Illuminate\Support\Facades\Schema;

trait OrderIdCompatTrait
{
    /**
     * 处理ID参数兼容（支持OID和数字ID，统一转换为数字ID）
     */
    protected function processOrderIdParam(string $paramName = 'order_id'): int
    {
        $order_id = $this->request->input($paramName, '');

        if (is_string($order_id) && strlen($order_id) === 8 && ctype_alnum($order_id)) {
            // 8位字符的OID，查询转换为数字ID
            if (! Schema::hasColumn('orders', 'oid')) {
                $this->error('Order not found');
            }

            $order = $this->model
                ->where('orders.oid', $order_id)
                ->where('user_id', $this->user_id)
                ->first();

            if (! $order) {
                $this->error('Order not found');
            }

            return $order->id;
        }

        return (int) $order_id;
    }

    /**
     * 处理数组参数中的ID兼容（用于renew和reissue）
     */
    protected function processOrderIdParamInArray(array &$params, string $paramName = 'order_id'): void
    {
        if (isset($params[$paramName])) {
            $order_id = $params[$paramName];

            // 如果是8位字符的OID，查询转换为数字ID
            if (is_string($order_id) && strlen($order_id) === 8 && ctype_alnum($order_id)) {
                if (! Schema::hasColumn('orders', 'oid')) {
                    $this->error('Order not found');
                }

                $order = Order::where('oid', $order_id)
                    ->where('user_id', $this->user_id)
                    ->first();
                if (! $order) {
                    $this->error('Order not found');
                }
                $convertedId = $order->id;
            } elseif (is_numeric($order_id)) {
                $convertedId = (int) $order_id;
            } else {
                $this->error('Invalid order ID');
            }

            // 如果是V1版本的oid参数，转换为order_id参数
            if ($paramName === 'oid') {
                unset($params['oid']);
                $params['order_id'] = $convertedId;
            } else {
                $params[$paramName] = $convertedId;
            }
        }
    }
}
