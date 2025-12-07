<?php

namespace App\Services\Notification\Guards;

use App\Models\NotificationTemplate;
use App\Services\Notification\DTOs\NotificationIntent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class ChannelGuardManager
{
    /**
     * @param  array<string, NotificationTemplate>  $channelTemplates
     * @return array{allowed: array<string>, rejected: array<string, string>}
     */
    public function filter(Model $notifiable, NotificationIntent $intent, array $channelTemplates): array
    {
        $allowed = [];
        $rejected = [];
        $guardClasses = Config::get('notification.guards', []);

        foreach ($channelTemplates as $channel => $template) {
            $isAllowed = true;
            foreach ($guardClasses as $guardClass) {
                $guard = app($guardClass);
                if (! $guard instanceof ChannelGuardInterface) {
                    continue;
                }

                if (! $guard->allow($notifiable, $channel, $template, $intent)) {
                    $isAllowed = false;
                    $rejected[$channel] = $guard->reason() ?? ($guardClass.' 拒绝发送');
                    break;
                }
            }

            if ($isAllowed) {
                $allowed[] = $channel;
            }
        }

        return [
            'allowed' => $allowed,
            'rejected' => $rejected,
        ];
    }
}
