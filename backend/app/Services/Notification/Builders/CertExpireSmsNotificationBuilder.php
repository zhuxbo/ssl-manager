<?php

namespace App\Services\Notification\Builders;

use App\Models\Order;
use App\Models\User;
use App\Services\Notification\DTOs\NotificationIntent;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Order\AutoRenewService;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class CertExpireSmsNotificationBuilder implements NotificationBuilderInterface
{
    public function __construct(
        private readonly AutoRenewService $autoRenewService
    ) {}

    public function build(NotificationIntent $intent, Model $notifiable): NotificationPayload
    {
        if (! $notifiable instanceof User) {
            throw new RuntimeException('通知接收者必须为用户');
        }

        $mobile = ($intent->context['mobile'] ?? '') ?: $notifiable->mobile;
        if (! $mobile) {
            throw new RuntimeException('手机号为空');
        }

        $orders = Order::with(['product', 'latestCert', 'user'])
            ->whereHas('product')
            ->whereHas('latestCert', function ($query) {
                $query->where('status', 'active')
                    ->whereBetween('expires_at', [now(), now()->addDays(14)])
                    ->orderBy('expires_at');
            })
            ->where('user_id', $notifiable->id)
            ->get();

        $certificates = [];
        foreach ($orders as $order) {
            // 检查自动任务是否会实际执行
            $willAutoRenew = $this->autoRenewService->willAutoRenewExecute($order, $notifiable);
            $willAutoReissue = $this->autoRenewService->willAutoReissueExecute($order, $notifiable);

            // 如果自动任务会实际执行，检查委托有效性
            if ($willAutoRenew || $willAutoReissue) {
                $ca = strtolower($order->product->ca ?? '');
                $domains = $order->latestCert->alternative_names;
                $delegationValid = $this->autoRenewService->checkDelegationValidity($notifiable->id, $domains, $ca);

                if ($delegationValid) {
                    // 委托有效，完全跳过该证书（不发通知）
                    continue;
                }
            }

            // 自动任务不会执行或委托无效，加入通知列表
            $certificates[] = [
                'domain' => $order->latestCert->common_name,
                'expire_at' => $order->latestCert->expires_at->format('Y-m-d'),
            ];
        }

        if (empty($certificates)) {
            throw new RuntimeException('14天内没有需要通知的到期证书');
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
