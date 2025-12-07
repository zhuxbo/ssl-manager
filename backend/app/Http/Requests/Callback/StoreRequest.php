<?php

namespace App\Http\Requests\Callback;

use App\Http\Requests\BaseRequest;

class StoreRequest extends BaseRequest
{
    protected function prepareForValidation(): void
    {
        // 先执行父类的处理逻辑
        parent::prepareForValidation();

        // 如果status为0，确保url和token为空字符串而不是null
        if ($this->input('status') == 0) {
            $this->merge([
                'url' => '',
                'token' => '',
            ]);
        }
    }

    public function rules(): array
    {
        // 如果status为1，再验证url和token
        return [
            'user_id' => 'required|integer|exists:users,id|unique:callbacks',
            'url' => 'nullable|required_if:status,1|url|max:255',
            'token' => 'nullable|required_if:status,1|string|max:255',
            'status' => 'required|integer|in:0,1',
        ];
    }
}
