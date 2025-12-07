<?php

namespace App\Http\Requests\User;

use App\Http\Requests\BaseRequest;

class CreateUserRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'username' => 'nullable|string|min:3|max:20|unique:users',
            'email' => 'required|string|email|max:50|unique:users',
        ];
    }
}
