<?php

namespace App\Services\Notification\Builders;

use App\Bootstrap\ApiExceptions;
use App\Models\Order;
use App\Models\User;
use App\Services\Notification\DTOs\NotificationIntent;
use App\Services\Notification\DTOs\NotificationPayload;
use DateMalformedStringException;
use DateTime;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class CertExpireMailNotificationBuilder implements NotificationBuilderInterface
{
    public function build(NotificationIntent $intent, Model $notifiable): NotificationPayload
    {
        if (! $notifiable instanceof User) {
            throw new RuntimeException('通知接收者必须为用户');
        }

        $email = ($intent->context['email'] ?? '') ?: $notifiable->email;
        if (! $email) {
            throw new RuntimeException('邮箱为空');
        }

        $siteUrl = get_system_setting('site', 'url', '/');
        $siteName = get_system_setting('site', 'name', 'SSL证书管理系统');

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
            try {
                $daysLeft = (int) (new DateTime)->diff(new DateTime((string) $order->latestCert->expires_at))->format('%a');
            } catch (DateMalformedStringException $e) {
                app(ApiExceptions::class)->logException($e);
                $daysLeft = 0;
            }

            $certificates[] = [
                'domain' => $order->latestCert->common_name,
                'expire_at' => $order->latestCert->expires_at->format('Y-m-d'),
                'days_left' => $daysLeft,
            ];
        }

        if (empty($certificates)) {
            throw new RuntimeException('30天内没有到期的证书');
        }

        $subject = 'SSL证书到期提醒 ['.$siteName.']';
        $data = [
            'username' => $notifiable->username,
            'email' => $email,
            'site_name' => $siteName,
            'site_url' => $siteUrl,
            'certificates' => $certificates,
            'subject' => $subject,
        ];

        return new NotificationPayload($data, ['mail']);
    }
}
