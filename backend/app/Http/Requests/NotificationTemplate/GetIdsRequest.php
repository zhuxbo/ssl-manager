<?php

namespace App\Http\Requests\NotificationTemplate;

use App\Http\Requests\BaseRequest;

class GetIdsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:notification_templates,id',
        ];
    }
}
