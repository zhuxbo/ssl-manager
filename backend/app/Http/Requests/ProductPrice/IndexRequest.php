<?php

namespace App\Http\Requests\ProductPrice;

use App\Http\Requests\BaseRequest;

class IndexRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'currentPage' => 'nullable|integer|min:1',
            'pageSize' => 'nullable|integer|min:1|max:100',
            'quickSearch' => 'nullable|string|max:100',
            'product_id' => 'nullable|integer',
            'level_code' => 'nullable|string|min:3|max:20',
            'period' => 'nullable|integer',
        ];
    }
}
