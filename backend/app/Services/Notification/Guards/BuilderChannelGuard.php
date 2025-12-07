<?php

namespace App\Services\Notification\Guards;

use App\Models\NotificationTemplate;
use App\Services\Notification\DTOs\NotificationIntent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class BuilderChannelGuard implements ChannelGuardInterface
{
    protected ?string $reasonMessage = null;

    public function allow(Model $notifiable, string $channel, NotificationTemplate $template, NotificationIntent $intent): bool
    {
        $this->reasonMessage = null;

        $map = Config::get('notification.builders', []);
        $key = "$intent->code.$channel";

        // 检查是否存在明确配置
        if (array_key_exists($key, $map)) {
            $class = $map[$key];
            // 空字符串表示明确禁用该组合
            if (empty($class)) {
                $this->reasonMessage = "通知构建器已禁用: $key";

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
