<?php

namespace Plugins\Invoice\Requests;

use App\Http\Requests\BaseRequest;

class UpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'remark' => 'nullable|string|max:500',
            'status' => 'integer|in:0,1,2',
        ];
    }
}
