<?php

namespace App\Services\Notification\Guards;

use App\Models\NotificationTemplate;
use App\Services\Notification\DTOs\NotificationIntent;
use Illuminate\Database\Eloquent\Model;

interface ChannelGuardInterface
{
    public function allow(Model $notifiable, string $channel, NotificationTemplate $template, NotificationIntent $intent): bool;

    public function reason(): ?string;
}
