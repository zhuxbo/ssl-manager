<?php

namespace App\Http\Requests\SettingGroup;

use App\Http\Requests\BaseRequest;
use App\Models\SettingGroup;

class StoreRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:50',
                function ($_attribute, $value, $fail) {
                    $query = SettingGroup::where('name', $value);

                    if ($query->exists()) {
                        $fail('该设置组名称已存在。');
                    }
                },
            ],
            'title' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'weight' => 'integer|min:0|max:10000',
        ];
    }
}
