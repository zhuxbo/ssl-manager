<?php

namespace App\Http\Requests\UserLevel;

use App\Http\Requests\BaseRequest;

class UpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        $userLevelId = $this->route('id', 0);

        return [
            'name' => 'required|string|min:3|max:20|unique:user_levels,name,'.$userLevelId,
            'code' => 'required|string|min:3|max:20|unique:user_levels,code,'.$userLevelId,
            'custom' => 'required|integer|in:0,1',
            'cost_rate' => 'required|numeric|min:1',
            'weight' => 'required|integer|min:1|max:10000',
        ];
    }
}
