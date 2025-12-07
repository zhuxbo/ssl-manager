<?php

namespace App\Http\Requests\Fund;

use App\Http\Requests\BaseRequest;

class StoreRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:users,id',
            'amount' => 'required|numeric|min:0',
            'type' => 'required|string|in:addfunds,refunds,deduct,reverse',
            'pay_method' => 'required|string',
            'pay_sn' => 'nullable|string',
            'remark' => 'nullable|string|max:500',
            'status' => 'required|integer|in:0,1,2',
        ];
    }
}
