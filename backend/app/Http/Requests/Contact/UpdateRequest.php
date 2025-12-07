<?php

namespace App\Http\Requests\Contact;

use App\Http\Requests\BaseRequest;

class UpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:users,id',
            'last_name' => 'required|string|max:50',
            'first_name' => 'required|string|max:50',
            'identification_number' => 'nullable|string|max:100',
            'title' => 'required|string|max:50',
            'email' => 'required|string|email|max:100',
            'phone' => 'required|string|max:20',
        ];
    }
}
