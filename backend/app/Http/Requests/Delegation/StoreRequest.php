<?php

namespace App\Http\Requests\Delegation;

use App\Http\Requests\BaseRequest;

class StoreRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:users,id',
            'zone' => 'required|string|max:255',
            'prefix' => 'required|in:_certum,_pki-validation,_dnsauth,_acme-challenge',
        ];
    }
}
