<?php

namespace App\Http\Requests\Chain;

use App\Http\Requests\BaseRequest;

class UpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        $chainId = $this->route('id', 0);

        return [
            'common_name' => 'required|string|max:255|unique:chains,common_name,'.$chainId,
            'intermediate_cert' => 'required|string',
        ];
    }
}
