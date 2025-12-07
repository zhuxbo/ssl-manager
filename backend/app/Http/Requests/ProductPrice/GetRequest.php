<?php

namespace App\Http\Requests\ProductPrice;

use App\Http\Requests\BaseRequest;

class GetRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'level_codes' => ['required', 'array'],
            'level_codes.*' => ['required', 'string', 'exists:user_levels,code'],
        ];
    }
}
