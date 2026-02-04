<?php

namespace App\Http\Requests\Organization;

use App\Http\Requests\BaseRequest;

class UpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:users,id',
            'name' => 'required|string|max:100',
            'registration_number' => 'required|string|max:50',
            'country' => 'required|string|max:50',
            'state' => 'required|string|max:50',
            'city' => 'required|string|max:50',
            'address' => 'required|string|max:255',
            'postcode' => 'required|string|min:3|max:20',
            'phone' => 'required|string|max:20',
        ];
    }
}
