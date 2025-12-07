<?php

namespace App\Services\Notification\Guards;

use App\Models\NotificationTemplate;
use App\Services\Notification\DTOs\NotificationIntent;
use Illuminate\Database\Eloquent\Model;

class UserPreferenceGuard implements ChannelGuardInterface
{
    protected ?string $reasonMessage = null;

    public function allow(Model $notifiable, string $channel, NotificationTemplate $template, NotificationIntent $intent): bool
    {
        $this->reasonMessage = null;

        if (! method_exists($notifiable, 'allowsNotificationChannel')) {
            return true;
        }

        $preferenceType = $this->resolvePreferenceType($template->code);
        if (! $notifiable->allowsNotificationChannel($channel, $preferenceType)) {
            $this->reasonMessage = '用户关闭了该通道';

            return false;
        }

        return true;
    }

    public function reason(): ?string
    {
        return $this->reasonMessage;
    }

    protected function resolvePreferenceType(string $code): string
    {
        foreach (['_html', '_text'] as $suffix) {
            if (str_ends_with($code, $suffix)) {
                return substr($code, 0, -strlen($suffix));
            }
        }

        return $code;
    }
}
