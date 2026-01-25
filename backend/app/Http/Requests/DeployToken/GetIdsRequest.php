<?php

namespace App\Http\Requests\DeployToken;

use App\Http\Requests\BaseRequest;

class GetIdsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:deploy_tokens,id',
        ];
    }
}
