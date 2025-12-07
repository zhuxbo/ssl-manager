<?php

namespace App\Http\Requests\Organization;

use App\Http\Requests\BaseRequest;

class IndexRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'currentPage' => 'nullable|integer|min:1',
            'pageSize' => 'nullable|integer|min:1|max:100',
            'quickSearch' => 'nullable|string|max:100',
            'user_id' => 'nullable|integer|min:1',
            'username' => 'nullable|string|max:20',
            'name' => 'nullable|string|max:100',
            'registration_number' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'created_at' => 'nullable|array|size:2',
            'created_at.*' => 'string|date_format:Y-m-d\TH:i:s.v\Z',
        ];
    }
}
