<?php

namespace App\Http\Requests\Notification;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class ResendRequest extends BaseRequest
{
    public function rules(): array
    {
        $channels = config('notification.available_channels', []);

        return [
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string', Rule::in($channels)],
        ];
    }
}
