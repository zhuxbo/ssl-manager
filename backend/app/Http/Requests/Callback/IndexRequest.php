<?php

namespace App\Http\Requests\Callback;

use App\Http\Requests\BaseRequest;

class IndexRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'currentPage' => 'nullable|integer|min:1',
            'pageSize' => 'nullable|integer|min:1|max:100',
            'username' => 'nullable|string|max:20',
            'url' => 'nullable|string|max:255',
            'status' => 'nullable|integer|in:0,1',
        ];
    }
}
