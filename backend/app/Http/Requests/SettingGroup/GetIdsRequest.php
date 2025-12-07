<?php

namespace App\Http\Requests\SettingGroup;

use App\Http\Requests\BaseRequest;

class GetIdsRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:setting_groups,id',
        ];
    }
}
