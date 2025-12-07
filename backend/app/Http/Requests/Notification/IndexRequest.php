<?php

namespace App\Http\Requests\Notification;

use App\Http\Requests\BaseRequest;
use App\Models\Notification;
use Illuminate\Validation\Rule;

class IndexRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'currentPage' => ['nullable', 'integer', 'min:1'],
            'pageSize' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => [
                'nullable', Rule::in([
                    Notification::STATUS_PENDING,
                    Notification::STATUS_SENDING,
                    Notification::STATUS_SENT,
                    Notification::STATUS_FAILED,
                ]),
            ],
            'template_code' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'notifiable_type' => ['nullable', 'string', 'max:255'],
            'created_at' => ['nullable', 'array', 'size:2'],
            'created_at.*' => ['date'],
        ];
    }
}
