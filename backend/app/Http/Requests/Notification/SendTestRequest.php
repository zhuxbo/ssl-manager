<?php

namespace App\Http\Requests\Notification;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class SendTestRequest extends BaseRequest
{
    public function rules(): array
    {
        $channels = config('notification.available_channels', []);

        return [
            'notifiable_type' => ['required', 'string', 'max:255'],
            'notifiable_id' => ['required', 'integer'],
            'template_type' => ['required', 'string', 'max:100'],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string', Rule::in($channels)],
            'data' => ['nullable', 'array'],
        ];
    }
}
