<?php

namespace App\Http\Requests\Invoice;

use App\Http\Requests\BaseRequest;

class GetIdsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:invoices,id',
        ];
    }
}
