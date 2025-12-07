<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;

class StoreRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'username' => 'required|string|min:3|max:20|unique:admins',
            'password' => 'required|string|min:6|max:32',
            'email' => 'nullable|email|max:50|unique:admins',
            'mobile' => 'nullable|string|unique:admins',
            'status' => 'required|integer|in:0,1',
        ];
    }
}
