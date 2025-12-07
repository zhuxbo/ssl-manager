<?php

namespace App\Services\Notification\Guards;

use App\Models\NotificationTemplate;
use App\Services\Notification\ChannelManager;
use App\Services\Notification\DTOs\NotificationIntent;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class SystemChannelGuard implements ChannelGuardInterface
{
    protected ?string $reasonMessage = null;

    public function __construct(protected ChannelManager $channelManager) {}

    public function allow(Model $notifiable, string $channel, NotificationTemplate $template, NotificationIntent $intent): bool
    {
        $this->reasonMessage = null;

        try {
            $channelDriver = $this->channelManager->channel($channel);
        } catch (InvalidArgumentException) {
            $this->reasonMessage = '通道未实现';

            return false;
        }

        if (method_exists($channelDriver, 'isAvailable') && ! $channelDriver->isAvailable()) {
            $this->reasonMessage = '通道未配置';

            return false;
        }

        return true;
    }

    public function reason(): ?string
    {
        return $this->reasonMessage;
    }
}
