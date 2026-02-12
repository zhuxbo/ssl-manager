<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\BaseRequest;

class IndexRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'currentPage' => 'nullable|integer|min:1',
            'pageSize' => 'nullable|integer|min:1|max:100',
            'quickSearch' => 'nullable|string|max:100',
            'brand' => 'nullable|string|max:50',
            'product_type' => 'nullable|string|in:ssl,codesign,smime,docsign',
            'encryption_standard' => 'nullable|string|in:international,chinese',
            'encryption_alg' => 'nullable|string|in:rsa,ecdsa,sm2',
            'validation_type' => 'nullable|string|in:dv,ov,ev',
            'name_type' => 'nullable|string|in:standard,wildcard,ipv4,ipv6',
            'domains' => 'nullable|string|in:single',
            'status' => 'nullable|integer|in:0,1',
        ];
    }
}
