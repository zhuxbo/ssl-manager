<?php

namespace App\Http\Requests\Delegation;

use App\Http\Requests\BaseRequest;

class UpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'regen_label' => 'nullable|boolean',
        ];
    }
}
