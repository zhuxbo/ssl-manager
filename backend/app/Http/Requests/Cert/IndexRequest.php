<?php

namespace App\Http\Requests\Cert;

use App\Http\Requests\BaseRequest;

class IndexRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'currentPage' => 'nullable|integer|min:1',
            'pageSize' => 'nullable|integer|min:1|max:100',
            'order_id' => 'nullable|integer|min:1',
            'domain' => 'nullable|string|max:100',
            'issued_at' => 'nullable|array|size:2',
            'issued_at.*' => 'string|date_format:Y-m-d\TH:i:s.v\Z',
            'expires_at' => 'nullable|array|size:2',
            'expires_at.*' => 'string|date_format:Y-m-d\TH:i:s.v\Z',
            'status' => 'nullable|string|in:activating,archived',
        ];
    }
}
