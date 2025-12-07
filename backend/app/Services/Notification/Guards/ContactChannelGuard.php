<?php

namespace App\Services\Notification\Guards;

use App\Models\NotificationTemplate;
use App\Services\Notification\DTOs\NotificationIntent;
use Illuminate\Database\Eloquent\Model;

class ContactChannelGuard implements ChannelGuardInterface
{
    protected ?string $reasonMessage = null;

    public function allow(Model $notifiable, string $channel, NotificationTemplate $template, NotificationIntent $intent): bool
    {
        $this->reasonMessage = null;

        $context = $intent->context;
        if ($channel === 'mail') {
            $email = $context['email'] ?? $notifiable->email ?? null;
            if (! $email) {
                $this->reasonMessage = '收件人邮箱为空';

                return false;
            }
        }

        if ($channel === 'sms') {
            $mobile = $context['mobile'] ?? $notifiable->mobile ?? null;
            if (! $mobile) {
                $this->reasonMessage = '收件人手机号为空';

                return false;
            }
        }

        return true;
    }

    public function reason(): ?string
    {
        return $this->reasonMessage;
    }
}
