<?php

namespace App\Http\Requests\NotificationTemplate;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends BaseRequest
{
    public function rules(): array
    {
        $availableChannels = config('notification.available_channels', []);

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:100'],
            'content' => ['required', 'string'],
            'variables' => ['nullable', 'array'],
            'example' => ['nullable', 'string'],
            'status' => ['required', 'integer', Rule::in([0, 1])],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['string', Rule::in($availableChannels)],
        ];
    }
}
