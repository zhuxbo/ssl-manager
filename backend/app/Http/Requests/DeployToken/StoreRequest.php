<?php

namespace App\Http\Requests\DeployToken;

use App\Http\Requests\BaseRequest;

class StoreRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:users,id|unique:deploy_tokens',
            'token' => 'required|string|alpha_num|size:32',
            'allowed_ips' => 'nullable|array',
            'allowed_ips.*' => 'ip',
            'rate_limit' => 'nullable|integer|min:1|max:1000',
            'status' => 'nullable|integer|in:0,1',
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
                'allowed_ips' => array_values(array_filter($this->allowed_ips, function ($ip) {
                    return ! empty(trim($ip));
                })),
            ]);
        }
    }
}
