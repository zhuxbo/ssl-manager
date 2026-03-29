<?php

namespace App\Http\Requests\User;

use App\Http\Requests\BaseRequest;

class StoreRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'username' => [
                'required',
                'string',
                'min:3',
                'max:20',
                'unique:users',
                function ($attribute, $value, $fail) {
                    if (! preg_match('/^[a-zA-Z0-9\x{4e00}-\x{9fa5}_-]+$/u', $value)) {
                        $fail('用户名只能包含字母、数字、中文、下划线和短横线');
                    }
                    if (preg_match('/^1[3-9]\d{9}$/', $value)) {
                        $fail('用户名不能是手机号');
                    }
                },
            ],
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
