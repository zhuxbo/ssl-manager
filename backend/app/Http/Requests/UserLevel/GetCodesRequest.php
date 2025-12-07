<?php

namespace App\Http\Requests\UserLevel;

use App\Http\Requests\BaseRequest;

class GetCodesRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'codes' => 'required|array',
            'codes.*' => 'string|exists:user_levels,code',
        ];
    }
}
