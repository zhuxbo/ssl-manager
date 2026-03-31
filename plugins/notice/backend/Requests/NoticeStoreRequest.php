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
            'position' => 'nullable|string|in:dashboard,order,product,popup',
            'is_active' => 'nullable|boolean',
            'sort' => 'nullable|integer|min:0|max:9999',
        ];
    }
}
