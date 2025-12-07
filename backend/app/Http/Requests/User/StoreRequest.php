<?php

namespace App\Http\Requests\User;

use App\Http\Requests\BaseRequest;

class StoreRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'username' => 'required|string|min:3|max:20|unique:users',
            'password' => 'required|string|min:6|max:32',
            'email' => 'nullable|string|email|max:50|unique:users',
            'mobile' => 'nullable|string|unique:users',
            'level_code' => 'required|string|exists:user_levels,code',
            'custom_level_code' => 'nullable|string|exists:user_levels,code',
            'credit_limit' => 'nullable|numeric',
            'status' => 'required|integer|in:0,1',
        ];
    }
}
