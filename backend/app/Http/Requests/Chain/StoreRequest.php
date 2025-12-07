<?php

namespace App\Http\Requests\Chain;

use App\Http\Requests\BaseRequest;

class StoreRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'common_name' => 'required|string|max:255|unique:chains',
            'intermediate_cert' => 'required|string',
        ];
    }
}
