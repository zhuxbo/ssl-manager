<?php

namespace App\Http\Requests\UserLevel;

use App\Http\Requests\BaseRequest;

class StoreRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|min:3|max:20|unique:user_levels',
            'code' => 'required|string|min:3|max:20|unique:user_levels',
            'custom' => 'required|integer|in:0,1',
            'cost_rate' => 'required|numeric|min:1',
            'weight' => 'required|integer|min:1|max:10000',
        ];
    }
}
