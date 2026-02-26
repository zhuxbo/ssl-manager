<?php

namespace Plugins\Easy\Requests;

use App\Http\Requests\BaseRequest;

class AgisoIndexRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'currentPage' => 'nullable|integer|min:1',
            'pageSize' => 'nullable|integer|min:1|max:100',
            'quickSearch' => 'nullable|string|max:100',
            'platform' => 'nullable|string|max:50',
            'product_code' => 'nullable|string|max:100',
            'order_id' => 'nullable|string|max:100',
            'tid' => 'nullable|string|max:100',
            'username' => 'nullable|string|max:20',
            'period' => 'nullable|integer|min:1|max:120',
            'type' => 'nullable|integer',
            'recharged' => 'nullable|integer|in:0,1',
            'created_at' => 'nullable|array|size:2',
            'created_at.*' => 'string|date_format:Y-m-d\TH:i:s.v\Z',
        ];
    }
}
