<?php

namespace App\Http\Requests\ProductPrice;

use App\Http\Requests\BaseRequest;

class SetRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'product_price' => ['required', 'array'],
        ];
    }
}
