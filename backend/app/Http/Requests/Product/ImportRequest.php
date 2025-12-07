<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\BaseRequest;

class ImportRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'source' => 'required|string',
            'brand' => 'nullable|string',
            'apiId' => 'nullable|string',
            'type' => 'nullable|string|in:new,update,all',
        ];
    }
}
