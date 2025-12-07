<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;

class UpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        $adminId = $this->route('id', 0);

        return [
            'username' => 'required|string|min:3|max:20|unique:admins,username,'.$adminId,
            'password' => 'nullable|string|min:6|max:32',
            'email' => 'nullable|email|unique:admins,email,'.$adminId,
            'mobile' => 'nullable|string|unique:admins,mobile,'.$adminId,
            'status' => 'required|integer|in:0,1',
        ];
    }
}
