<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;

class UpdateProfileRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'email' => 'required|email|max:255',
            'mobile' => 'required|numeric|digits:11',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => '邮箱不能为空',
            'email.email' => '邮箱格式不正确',
            'email.max' => '邮箱长度不能超过255个字符',
            'mobile.required' => '手机号不能为空',
            'mobile.numeric' => '手机号格式不正确',
            'mobile.digits' => '手机号长度不正确',
        ];
    }
}
