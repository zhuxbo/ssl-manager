<?php

namespace App\Services\Notification\Builders;

use App\Models\Order;
use App\Models\User;
use App\Services\Notification\DTOs\NotificationIntent;
use App\Services\Notification\DTOs\NotificationPayload;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class CertIssuedSmsNotificationBuilder implements NotificationBuilderInterface
{
    public function build(NotificationIntent $intent, Model $notifiable): NotificationPayload
    {
        if (! $notifiable instanceof User) {
            throw new RuntimeException('通知接收者必须为用户');
        }

        $orderId = (int) ($intent->context['order_id'] ?? 0);
        if (! $orderId) {
            throw new RuntimeException('订单ID不存在');
        }

        $order = Order::with(['user', 'product', 'latestCert'])
            ->whereHas('user')
            ->whereHas('product')
            ->whereHas('latestCert', fn ($query) => $query->where('status', 'active'))
            ->find($orderId);

        if (! $order) {
            throw new RuntimeException('订单不存在或未签发');
        }

        $mobile = ($intent->context['mobile'] ?? '') ?: $notifiable->mobile;
        if (! $mobile) {
            throw new RuntimeException('手机号为空');
        }

        $domain = $order->latestCert->common_name;
        $productName = $order->product->name;

        $data = [
            'order_id' => $order->id,
            'domain' => $domain,
            'product' => $productName,
            'username' => $notifiable->username,
            'mobile' => $mobile,
            'sms' => [
                // EasySms 的 data 字段只包含模板变量
                'username' => $notifiable->username,
                'domain' => $domain,
                'product' => $productName,
            ],
        ];

        return new NotificationPayload($data, ['sms']);
    }
}
