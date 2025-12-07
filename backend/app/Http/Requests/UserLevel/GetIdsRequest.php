<?php

namespace App\Http\Requests\UserLevel;

use App\Http\Requests\BaseRequest;

class GetIdsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:user_levels,id',
        ];
    }
}
