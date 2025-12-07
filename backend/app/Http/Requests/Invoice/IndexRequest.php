<?php

namespace App\Http\Requests\Invoice;

use App\Http\Requests\BaseRequest;

class IndexRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'currentPage' => 'nullable|integer|min:1',
            'pageSize' => 'nullable|integer|min:1|max:100',
            'quickSearch' => 'nullable|string|max:100',
            'id' => 'nullable|integer|min:1',
            'username' => 'nullable|string|max:20',
            'organization' => 'nullable|string|max:100',
            'email' => 'nullable|string|max:100',
            'amount' => 'nullable|array|size:1,2',
            'amount.*' => 'numeric',
            'status' => 'nullable|integer|in:0,1,2',
            'created_at' => 'nullable|array|size:2',
            'created_at.*' => 'string|date_format:Y-m-d\TH:i:s.v\Z',
        ];
    }
}
