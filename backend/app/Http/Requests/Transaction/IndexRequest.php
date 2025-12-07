<?php

namespace App\Http\Requests\Transaction;

use App\Http\Requests\BaseRequest;

class IndexRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'currentPage' => 'nullable|integer|min:1',
            'pageSize' => 'nullable|integer|min:1|max:100',
            'quickSearch' => 'nullable|string|max:100',
            'username' => 'nullable|string|max:20',
            'type' => 'nullable|string',
            'transaction_id' => 'nullable|string',
            'amount' => 'nullable|array|size:1,2',
            'amount.*' => 'numeric',
            'created_at' => 'nullable|array|size:2',
            'created_at.*' => 'string|date_format:Y-m-d\TH:i:s.v\Z',
        ];
    }
}
