<?php

use App\Models\Cert;
use App\Models\Order;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * 修复使用委托验证的证书的 dcv 数据
     * 添加缺失的 is_delegate 和 ca 标记
     */
    public function up(): void
    {
        // 查找所有使用委托验证的证书（params 中 validation_method 为 delegation）
        $certs = Cert::where('params->validation_method', 'delegation')->get();

        foreach ($certs as $cert) {
            $dcv = $cert->dcv;

            // 如果 dcv 中没有 is_delegate 标记，则添加
            if (($dcv['method'] ?? '') === 'txt' && empty($dcv['is_delegate'])) {
                $dcv['is_delegate'] = true;

                // 从订单关联的产品获取 CA 信息
                $order = Order::with('product')->find($cert->order_id);
                if ($order && $order->product) {
                    $dcv['ca'] = strtolower($order->product->ca);
                }

                $cert->dcv = $dcv;
                $cert->save();
            }
        }
    }

    public function down(): void
    {
        // 无需回滚，保留 is_delegate 标记不会影响功能
    }
};
