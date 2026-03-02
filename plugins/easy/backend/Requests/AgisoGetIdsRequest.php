<?php

namespace Plugins\Easy\Requests;

use App\Http\Requests\BaseRequest;

class AgisoGetIdsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'ID列表不能为空',
            'ids.array' => 'ID必须为数组格式',
            'ids.min' => '至少选择一条记录',
            'ids.*.required' => 'ID不能为空',
            'ids.*.integer' => 'ID必须为整数',
            'ids.*.min' => 'ID必须大于0',
        ];
    }
}
