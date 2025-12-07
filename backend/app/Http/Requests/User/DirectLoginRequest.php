<?php

namespace App\Http\Requests\User;

use App\Http\Requests\BaseRequest;

class DirectLoginRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:users,id',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => '用户ID不能为空',
            'user_id.integer' => '用户ID必须是整数',
            'user_id.exists' => '用户不存在',
        ];
    }
}
