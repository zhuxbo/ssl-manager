<?php

namespace App\Http\Requests\NotificationTemplate;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        $id = $this->route('id');
        $availableChannels = config('notification.available_channels', []);

        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'code' => [
                'sometimes',
                'string',
                'max:100',
            ],
            'content' => ['sometimes', 'string'],
            'variables' => ['nullable', 'array'],
            'example' => ['nullable', 'string'],
            'status' => ['sometimes', 'integer', Rule::in([0, 1])],
            'channels' => ['sometimes', 'array', 'min:1'],
            'channels.*' => ['string', Rule::in($availableChannels)],
        ];
    }
}
