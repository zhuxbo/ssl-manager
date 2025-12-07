<?php

namespace App\Http\Requests\Chain;

use App\Http\Requests\BaseRequest;

class IndexRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'currentPage' => 'nullable|integer|min:1',
            'pageSize' => 'nullable|integer|min:1|max:100',
            'common_name' => 'nullable|string|max:255',
        ];
    }
}
