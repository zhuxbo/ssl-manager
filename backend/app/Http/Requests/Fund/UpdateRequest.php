<?php

namespace App\Http\Requests\Fund;

use App\Http\Requests\BaseRequest;

class UpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'pay_sn' => 'nullable|string',
            'remark' => 'nullable|string|max:500',
            'status' => 'nullable|integer|in:0,1,2',
        ];
    }
}
