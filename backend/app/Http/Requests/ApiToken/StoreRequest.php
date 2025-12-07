<?php

namespace App\Http\Requests\ApiToken;

use App\Http\Requests\BaseRequest;

class StoreRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:users,id|unique:api_tokens',
            'token' => 'required|string|alpha_num|max:128',
            'allowed_ips' => 'nullable|array',
            'rate_limit' => 'nullable|integer',
            'status' => 'nullable|integer',
        ];
    }

    /**
     * 处理请求参数，过滤allowed_ips中的空值
     */
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if (isset($this->allowed_ips) && is_array($this->allowed_ips)) {
            $this->merge([
                'allowed_ips' => array_filter($this->allowed_ips, function ($ip) {
                    return ! empty($ip);
                }),
            ]);
        }
    }
}
