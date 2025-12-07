<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;

class UpdatePasswordRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'oldPassword' => 'required|string',
            'newPassword' => 'required|string|min:6|different:oldPassword',
        ];
    }

    public function messages(): array
    {
        return [
            'oldPassword.required' => '旧密码不能为空',
            'newPassword.required' => '新密码不能为空',
            'newPassword.min' => '新密码长度不能小于6个字符',
            'newPassword.different' => '新密码不能与旧密码相同',
        ];
    }
}
