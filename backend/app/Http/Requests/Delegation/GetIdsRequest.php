<?php

namespace App\Http\Requests\Delegation;

use App\Http\Requests\BaseRequest;

class GetIdsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:cname_delegations,id',
        ];
    }
}
