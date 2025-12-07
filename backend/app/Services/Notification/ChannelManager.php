<?php

namespace App\Services\Notification;

use App\Services\Notification\Channels\ChannelInterface;
use App\Services\Notification\Channels\MailChannel;
use App\Services\Notification\Channels\SmsChannel;
use InvalidArgumentException;

class ChannelManager
{
    /**
     * @var array<string, ChannelInterface>
     */
    protected array $channels;

    public function __construct(MailChannel $mailChannel, SmsChannel $smsChannel)
    {
        $this->channels = [
            'mail' => $mailChannel,
            'sms' => $smsChannel,
        ];
    }

    public function channel(string $name): ChannelInterface
    {
        if (! isset($this->channels[$name])) {
            throw new InvalidArgumentException("未知的通知通道: $name");
        }

        return $this->channels[$name];
    }
}
