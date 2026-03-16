<?php

namespace Plugins\Notice\Requests;

use App\Http\Requests\BaseRequest;

class NoticeStoreRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:200',
            'content' => 'required|string|max:5000',
            'type' => 'nullable|string|in:info,warning,success,error',
            'is_active' => 'nullable|boolean',
            'sort' => 'nullable|integer|min:0|max:9999',
        ];
    }
}
