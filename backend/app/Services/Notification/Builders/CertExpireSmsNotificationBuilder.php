<?php

namespace App\Services\Notification\Builders;

use App\Models\Order;
use App\Models\User;
use App\Services\Notification\DTOs\NotificationIntent;
use App\Services\Notification\DTOs\NotificationPayload;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class CertExpireSmsNotificationBuilder implements NotificationBuilderInterface
{
    public function build(NotificationIntent $intent, Model $notifiable): NotificationPayload
    {
        if (! $notifiable instanceof User) {
            throw new RuntimeException('通知接收者必须为用户');
        }

        $mobile = ($intent->context['mobile'] ?? '') ?: $notifiable->mobile;
        if (! $mobile) {
            throw new RuntimeException('手机号为空');
        }

        $orders = Order::with(['product', 'latestCert'])
            ->whereHas('product')
            ->whereHas('latestCert', function ($query) {
                $query->where('status', 'active')
                    ->whereBetween('expires_at', [now(), now()->addDays(30)])
                    ->orderBy('expires_at');
            })
            ->where('user_id', $notifiable->id)
            ->get();

        $certificates = [];
        foreach ($orders as $order) {
            $certificates[] = [
                'domain' => $order->latestCert->common_name,
                'expire_at' => $order->latestCert->expires_at->format('Y-m-d'),
            ];
        }

        if (empty($certificates)) {
            throw new RuntimeException('30天内没有到期的证书');
        }

        $domains = array_column($certificates, 'domain');
        $summaryParts = array_slice($domains, 0, 3);
        $summary = implode('、', $summaryParts);
        if (count($domains) > 3) {
            $summary .= ' 等'.count($domains).'个';
        }

        $data = [
            'username' => $notifiable->username,
            'mobile' => $mobile,
            'certificates' => $certificates,
            'sms' => [
                'username' => $notifiable->username,
                'certificates' => $summary,
            ],
        ];

        return new NotificationPayload($data, ['sms']);
    }
}
