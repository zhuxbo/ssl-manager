<?php

namespace App\Http\Requests\Callback;

use App\Http\Requests\BaseRequest;

class UpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        $callbackId = $this->route('id', 0);

        return [
            'user_id' => 'required|integer|exists:users,id|unique:callbacks,user_id,'.$callbackId,
            'url' => 'required|string|url|max:255',
            'token' => 'nullable|string|max:255',
            'status' => 'nullable|integer|in:0,1',
        ];
    }
}
