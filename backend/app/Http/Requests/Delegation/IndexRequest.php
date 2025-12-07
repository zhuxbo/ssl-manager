<?php

namespace App\Http\Requests\Delegation;

use App\Http\Requests\BaseRequest;

class IndexRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'currentPage' => 'nullable|integer|min:1',
            'pageSize' => 'nullable|integer|min:1|max:100',
            'quickSearch' => 'nullable|string|max:255',
            'user_id' => 'nullable|integer|min:1',
            'zone' => 'nullable|string|max:255',
            'prefix' => 'nullable|in:_certum,_pki-validation,_dnsauth,_acme-challenge',
            'valid' => 'nullable|boolean',
        ];
    }
}
