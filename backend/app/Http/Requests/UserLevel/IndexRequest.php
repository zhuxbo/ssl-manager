<?php

namespace App\Http\Requests\UserLevel;

use App\Http\Requests\BaseRequest;

class IndexRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'currentPage' => 'nullable|integer|min:1',
            'pageSize' => 'nullable|integer|min:1|max:100',
            'quickSearch' => 'nullable|string|max:100',
            'custom' => 'nullable|integer|in:0,1',
            'code' => 'nullable|string|max:100',
            'name' => 'nullable|string|max:100',
        ];
    }
}
